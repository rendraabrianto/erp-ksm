<?php

namespace App\Services;

use App\DTO\DeliveryOrderDTO;
use App\DTO\InventoryTransactionDTO;
use App\Models\Item;
use App\Models\SalesOrderDetail;
use App\Repositories\Contracts\DeliveryOrderRepositoryInterface;
use Illuminate\Support\Facades\DB;

class DeliveryOrderService
{
    public function __construct(
        private DeliveryOrderRepositoryInterface $repository,
        private DocumentSequenceService $documentSequenceService,
        private InventoryTransactionService $inventoryService,
        private AutoJournalService $autoJournalService,
        private AuditLogService $auditService,
    ) {
    }

    public function create(
        DeliveryOrderDTO $dto
    ) {
        return DB::transaction(
            function () use ($dto) {

                /*
                |--------------------------------------------------------------------------
                | Resolve Delivery Date
                |--------------------------------------------------------------------------
                |
                | Delivery date menjadi single source of truth untuk:
                |
                | - Delivery Order
                | - Stock Ledger
                | - Journal HPP
                |
                */

                $deliveryDate =
                    $dto->deliveryDate
                    ?? now()->toDateString();

                /*
                |--------------------------------------------------------------------------
                | Create Delivery Order Header
                |--------------------------------------------------------------------------
                */

                $do =
                    $this
                        ->repository
                        ->create([
                            'do_no' =>
                                $this
                                    ->documentSequenceService
                                    ->next('DO'),

                            'sales_order_id' =>
                                $dto->salesOrderId,

                            'delivery_date' =>
                                $deliveryDate,

                            'status' =>
                                'POSTED',

                            'remarks' =>
                                $dto->remarks,

                            'created_by' =>
                                $dto->createdBy,
                        ]);

                /*
                |--------------------------------------------------------------------------
                | Process Details
                |--------------------------------------------------------------------------
                */

                foreach (
                    $dto->lines
                    as $line
                ) {

                    /*
                    |--------------------------------------------------------------------------
                    | Sales Order Detail
                    |--------------------------------------------------------------------------
                    |
                    | salesOrderDetailId WAJIB.
                    |
                    | Kita tidak lagi memilih detail SO hanya berdasarkan item_id.
                    | Dengan demikian item yang sama dapat muncul pada beberapa
                    | baris Sales Order tanpa ambiguity.
                    |
                    | lockForUpdate mencegah dua proses Delivery Order paralel
                    | membaca remaining quantity yang sama.
                    |
                    */

                    $soDetail =
                        SalesOrderDetail::query()
                            ->whereKey(
                                $line->salesOrderDetailId
                            )
                            ->where(
                                'sales_order_id',
                                $dto->salesOrderId
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    /*
                    |--------------------------------------------------------------------------
                    | Validate Item Consistency
                    |--------------------------------------------------------------------------
                    */

                    if (
                        (int) $soDetail->item_id
                        !==
                        (int) $line->itemId
                    ) {
                        throw new \RuntimeException(
                            'Delivery order item does not match sales order detail.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Item + Accounting Mapping
                    |--------------------------------------------------------------------------
                    |
                    | Item.average_cost TIDAK digunakan sebagai sumber HPP.
                    |
                    | Inventory dan COGS account berasal dari Item Category.
                    |
                    */

                    $item =
                        Item::query()
                            ->with([
                                'category.inventoryAccount',
                                'category.cogsAccount',
                            ])
                            ->findOrFail(
                                $line->itemId
                            );

                    if (
                        !$item->category
                        ||
                        !$item
                            ->category
                            ->inventoryAccount
                    ) {
                        throw new \RuntimeException(
                            sprintf(
                                'Inventory account is not configured for item %s.',
                                $item->code
                            )
                        );
                    }

                    if (
                        !$item
                            ->category
                            ->cogsAccount
                    ) {
                        throw new \RuntimeException(
                            sprintf(
                                'COGS account is not configured for item %s.',
                                $item->code
                            )
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Check Remaining Sales Order Quantity
                    |--------------------------------------------------------------------------
                    */

                    $remainingQty =
                        (float) $soDetail->qty
                        -
                        (float) $soDetail->delivered_qty;

                    if (
                        (float) $line->qty
                        >
                        $remainingQty
                    ) {
                        throw new \RuntimeException(
                            'Delivery exceeds remaining order qty'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Post Inventory OUT
                    |--------------------------------------------------------------------------
                    |
                    | unitCost = 0 karena untuk STOCK OUT authoritative cost
                    | dihitung InventoryCostingService berdasarkan:
                    |
                    | warehouse_id + item_id
                    |
                    | sebelum transaksi.
                    |
                    | Delivery date diteruskan ke InventoryTransactionService.
                    |
                    */

                    $inventoryTransaction =
                        $this
                            ->inventoryService
                            ->post(
                                new InventoryTransactionDTO(
                                    warehouseId:
                                        $dto->warehouseId,

                                    itemId:
                                        $line->itemId,

                                    referenceType:
                                        'DELIVERY_ORDER',

                                    referenceId:
                                        $do->id,

                                    qtyIn:
                                        0,

                                    qtyOut:
                                        (float) $line->qty,

                                    unitCost:
                                        0,

                                    remarks:
                                        $line->remarks
                                        ?? 'Delivery Order',

                                    transactionDate:
                                        $deliveryDate,
                                )
                            );

                    /*
                    |--------------------------------------------------------------------------
                    | Delivery Order Detail
                    |--------------------------------------------------------------------------
                    |
                    | sales_order_detail_id memberikan traceability langsung
                    | terhadap baris SO yang dikirim.
                    |
                    | unit_cost berasal dari authoritative warehouse costing,
                    | bukan Item.average_cost.
                    |
                    */

                    $do
                        ->details()
                        ->create([
                            'sales_order_detail_id' =>
                                $soDetail->id,

                            'item_id' =>
                                $line->itemId,

                            'qty' =>
                                $line->qty,

                            'unit_cost' =>
                                $inventoryTransaction->unit_cost,

                            'remarks' =>
                                $line->remarks,
                        ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Update Sales Order Delivered Quantity
                    |--------------------------------------------------------------------------
                    */

                    $soDetail
                        ->increment(
                            'delivered_qty',
                            $line->qty
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | Accounting Mapping
                    |--------------------------------------------------------------------------
                    */

                    $cogsAccount =
                        $item
                            ->category
                            ->cogsAccount
                            ->code;

                    $inventoryAccount =
                        $item
                            ->category
                            ->inventoryAccount
                            ->code;

                    /*
                    |--------------------------------------------------------------------------
                    | HPP Amount
                    |--------------------------------------------------------------------------
                    |
                    | HPP berasal langsung dari InventoryTransactionService.
                    |
                    | Jangan menggunakan:
                    |
                    | qty * Item.average_cost
                    |
                    */

                    $amount =
                        (float)
                        $inventoryTransaction
                            ->total_cost;

                    /*
                    |--------------------------------------------------------------------------
                    | Auto Journal HPP
                    |--------------------------------------------------------------------------
                    |
                    | Dr COGS
                    | Cr Inventory
                    |
                    | Journal date harus sama dengan delivery date.
                    |
                    */

                    $this
                        ->autoJournalService
                        ->deliveryOrder(
                            cogsAccount:
                                $cogsAccount,

                            inventoryAccount:
                                $inventoryAccount,

                            amount:
                                $amount,

                            referenceId:
                                $do->id,

                            userId:
                                $dto->createdBy,

                            journalDate:
                                $deliveryDate,
                        );
                }

                /*
                |--------------------------------------------------------------------------
                | Audit Log
                |--------------------------------------------------------------------------
                */

                $this
                    ->auditService
                    ->log(
                        module:
                            'Delivery Order',

                        action:
                            'CREATE',

                        referenceType:
                            'DeliveryOrder',

                        referenceId:
                            $do->id,

                        oldValues:
                            null,

                        newValues: [
                            'do_no' =>
                                $do->do_no,

                            'delivery_date' =>
                                $deliveryDate,

                            'warehouse_id' =>
                                $dto->warehouseId,

                            'sales_order_id' =>
                                $dto->salesOrderId,
                        ]
                    );

                /*
                |--------------------------------------------------------------------------
                | Return
                |--------------------------------------------------------------------------
                */

                return $do->load(
                    'details'
                );
            }
        );
    }
}
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
    ) {}

    public function create(
        DeliveryOrderDTO $dto
    )
    {
        return DB::transaction(function () use ($dto) {

            /*
            |--------------------------------------------------------------------------
            | CREATE DELIVERY ORDER HEADER
            |--------------------------------------------------------------------------
            */

            $do = $this->repository->create([

                'do_no' =>
                    $this
                        ->documentSequenceService
                        ->next('DO'),

                'sales_order_id' =>
                    $dto->salesOrderId,

                'delivery_date' =>
                    now()->toDateString(),

                'status' =>
                    'POSTED',

                'remarks' =>
                    $dto->remarks,

                'created_by' =>
                    $dto->createdBy,
            ]);

            /*
            |--------------------------------------------------------------------------
            | PROCESS DETAIL
            |--------------------------------------------------------------------------
            */

            foreach ($dto->lines as $line) {

                /*
                |--------------------------------------------------------------------------
                | AMBIL SALES ORDER DETAIL
                |--------------------------------------------------------------------------
                */

                $soDetail =
                    SalesOrderDetail::where(
                        'sales_order_id',
                        $dto->salesOrderId
                    )
                    ->where(
                        'item_id',
                        $line->itemId
                    )
                    ->firstOrFail();

                /*
                |--------------------------------------------------------------------------
                | AMBIL ITEM + ACCOUNT
                |--------------------------------------------------------------------------
                |
                | Item.average_cost TIDAK digunakan sebagai sumber HPP.
                | Cost akan dihitung oleh InventoryCostingService.
                |--------------------------------------------------------------------------
                */

                $item =
                    Item::with([
                        'category.inventoryAccount',
                        'category.cogsAccount'
                    ])->findOrFail(
                        $line->itemId
                    );

                /*
                |--------------------------------------------------------------------------
                | CEK SISA QTY SALES ORDER
                |--------------------------------------------------------------------------
                */

                $remainingQty =
                    $soDetail->qty
                    -
                    $soDetail->delivered_qty;

                if (
                    $line->qty >
                    $remainingQty
                ) {

                    throw new \Exception(
                        'Delivery exceeds remaining order qty'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | INVENTORY TRANSACTION
                |--------------------------------------------------------------------------
                |
                | qtyOut dikirim ke InventoryTransactionService.
                |
                | unitCost = 0 karena untuk STOCK OUT,
                | costing engine yang menentukan biaya aktual.
                |--------------------------------------------------------------------------
                */

                $inventoryTransaction =
                    $this->inventoryService->post(

                        new InventoryTransactionDTO(

                            warehouseId :
                                $dto->warehouseId,

                            itemId :
                                $line->itemId,

                            referenceType :
                                'DELIVERY_ORDER',

                            referenceId :
                                $do->id,

                            qtyIn :
                                0,

                            qtyOut :
                                $line->qty,

                            unitCost :
                                0,

                            remarks :
                                'Delivery Order'
                        )
                    );

                /*
                |--------------------------------------------------------------------------
                | SIMPAN DETAIL DELIVERY ORDER
                |--------------------------------------------------------------------------
                |
                | Unit cost diambil dari hasil Inventory Costing,
                | BUKAN dari Item.average_cost.
                |--------------------------------------------------------------------------
                */

                $do->details()->create([

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
                | UPDATE DELIVERED QTY SALES ORDER
                |--------------------------------------------------------------------------
                */

                $soDetail->increment(
                    'delivered_qty',
                    $line->qty
                );

                /*
                |--------------------------------------------------------------------------
                | ACCOUNT HPP
                |--------------------------------------------------------------------------
                */

                $cogsAccount =
                    $item->category
                        ->cogsAccount
                        ->code;

                /*
                |--------------------------------------------------------------------------
                | ACCOUNT INVENTORY
                |--------------------------------------------------------------------------
                */

                $inventoryAccount =
                    $item->category
                        ->inventoryAccount
                        ->code;

                /*
                |--------------------------------------------------------------------------
                | HPP AMOUNT
                |--------------------------------------------------------------------------
                |
                | Ambil total cost LANGSUNG dari InventoryCosting.
                |
                | Jangan lagi:
                |
                | $line->qty * $item->average_cost
                |--------------------------------------------------------------------------
                */

                $amount =
                    (float)
                    $inventoryTransaction->total_cost;

                /*
                |--------------------------------------------------------------------------
                | AUTO JOURNAL HPP
                |--------------------------------------------------------------------------
                |
                | Dr HPP
                | Cr Persediaan
                |--------------------------------------------------------------------------
                */

                $this->autoJournalService
                    ->deliveryOrder(

                        cogsAccount :
                            $cogsAccount,

                        inventoryAccount :
                            $inventoryAccount,

                        amount :
                            $amount,

                        referenceId :
                            $do->id,

                        userId :
                            $dto->createdBy
                    );
            }

            /*
            |--------------------------------------------------------------------------
            | AUDIT LOG
            |--------------------------------------------------------------------------
            */

            $this->auditService->log(

                module :
                    'Delivery Order',

                action :
                    'CREATE',

                referenceType :
                    'DeliveryOrder',

                referenceId :
                    $do->id,

                oldValues :
                    null,

                newValues : [

                    'do_no' =>
                        $do->do_no,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | RETURN
            |--------------------------------------------------------------------------
            */

            return $do->load(
                'details'
            );
        });
    }
}
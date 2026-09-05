<?php

namespace App\Services;

use App\DTO\GoodsReceiptDTO;
use App\DTO\InventoryTransactionDTO;
use App\Models\Item;
use App\Models\PurchaseOrderDetail;
use App\Repositories\Contracts\GoodsReceiptRepositoryInterface;
use Illuminate\Support\Facades\DB;

class GoodsReceiptService
{
    public function __construct(
        private GoodsReceiptRepositoryInterface $repository,
        private DocumentSequenceService $documentSequenceService,
        private InventoryTransactionService $inventoryTransactionService,
        private AutoJournalService $autoJournalService,
        private AuditLogService $auditLogService,
    ) {
    }

    public function create(
        GoodsReceiptDTO $dto
    ) {
        return DB::transaction(
            function () use ($dto) {

                /*
                |--------------------------------------------------------------------------
                | Resolve Receipt Date
                |--------------------------------------------------------------------------
                |
                | Receipt date menjadi single source of truth untuk:
                |
                | - Goods Receipt
                | - Stock Ledger
                | - Journal
                |
                */

                $receiptDate =
                    $dto->receiptDate
                    ?? now()->toDateString();

                /*
                |--------------------------------------------------------------------------
                | Goods Receipt Header
                |--------------------------------------------------------------------------
                */

                $gr =
                    $this
                        ->repository
                        ->create([
                            'gr_no' =>
                                $this
                                    ->documentSequenceService
                                    ->next('GR'),

                            'purchase_order_id' =>
                                $dto->purchaseOrderId,

                            'receipt_date' =>
                                $receiptDate,

                            'status' =>
                                'POSTED',

                            'remarks' =>
                                $dto->remarks,

                            'created_by' =>
                                $dto->createdBy,
                        ]);

                /*
                |--------------------------------------------------------------------------
                | Goods Receipt Lines
                |--------------------------------------------------------------------------
                */

                foreach (
                    $dto->lines
                    as $line
                ) {

                    /*
                    |--------------------------------------------------------------------------
                    | Purchase Order Detail
                    |--------------------------------------------------------------------------
                    |
                    | purchaseOrderDetailId WAJIB.
                    |
                    | Kita tidak lagi mencari detail PO hanya berdasarkan item_id.
                    | Dengan demikian item yang sama boleh muncul pada lebih dari
                    | satu baris PO tanpa ambiguity.
                    |
                    | lockForUpdate mencegah dua proses Goods Receipt paralel
                    | membaca remaining quantity yang sama.
                    |
                    */

                    $poDetail =
                        PurchaseOrderDetail::query()
                            ->whereKey(
                                $line->purchaseOrderDetailId
                            )
                            ->where(
                                'purchase_order_id',
                                $dto->purchaseOrderId
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    /*
                    |--------------------------------------------------------------------------
                    | Validate Item Consistency
                    |--------------------------------------------------------------------------
                    |
                    | Detail PO yang dipilih harus benar-benar milik item
                    | yang dikirim pada Goods Receipt line.
                    |
                    */

                    if (
                        (int) $poDetail->item_id
                        !==
                        (int) $line->itemId
                    ) {
                        throw new \RuntimeException(
                            'Goods receipt item does not match purchase order detail.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Validate Remaining Purchase Order Quantity
                    |--------------------------------------------------------------------------
                    */

                    $orderedQty =
                        (float)
                        $poDetail->qty;

                    $receivedQty =
                        (float)
                        $poDetail->received_qty;

                    $remainingQty =
                        $orderedQty
                        -
                        $receivedQty;

                    if (
                        (float) $line->qty
                        >
                        $remainingQty
                    ) {
                        throw new \RuntimeException(
                            'Goods receipt exceeds remaining purchase order qty.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Item + Accounting Mapping
                    |--------------------------------------------------------------------------
                    */

                    $item =
                        Item::query()
                            ->with([
                                'category.inventoryAccount',
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

                    /*
                    |--------------------------------------------------------------------------
                    | Goods Receipt Detail
                    |--------------------------------------------------------------------------
                    |
                    | Traceability:
                    |
                    | GR Detail
                    |     -> Purchase Order Detail
                    |     -> Item
                    |
                    */

                    $gr
                        ->details()
                        ->create([
                            'purchase_order_detail_id' =>
                                $poDetail->id,

                            'item_id' =>
                                $line->itemId,

                            'qty_received' =>
                                $line->qty,

                            'unit_cost' =>
                                $line->unitPrice,

                            'remarks' =>
                                $line->remarks,
                        ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Update PO Received Quantity
                    |--------------------------------------------------------------------------
                    */

                    $poDetail
                        ->increment(
                            'received_qty',
                            $line->qty
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | Inventory Transaction
                    |--------------------------------------------------------------------------
                    |
                    | Stock IN diposting ke warehouse yang dipilih.
                    |
                    | Costing authoritative berasal dari:
                    |
                    | warehouse_id + item_id
                    |
                    | Bukan dari items.average_cost.
                    |
                    */

                    $this
                        ->inventoryTransactionService
                        ->post(
                            new InventoryTransactionDTO(
                                warehouseId:
                                    $dto->warehouseId,

                                itemId:
                                    $line->itemId,

                                referenceType:
                                    'GOODS_RECEIPT',

                                referenceId:
                                    $gr->id,

                                qtyIn:
                                    (float) $line->qty,

                                qtyOut:
                                    0,

                                unitCost:
                                    (float) $line->unitPrice,

                                remarks:
                                    $line->remarks
                                    ?? 'Goods Receipt',

                                transactionDate:
                                    $receiptDate,
                            )
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | Informational Last Purchase Price
                    |--------------------------------------------------------------------------
                    |
                    | last_purchase_price hanya informasi harga pembelian terakhir.
                    |
                    | items.average_cost TIDAK diupdate.
                    |
                    | Moving average authoritative berasal dari warehouse
                    | stock ledger / InventoryCostingService.
                    |
                    */

                    $item->update([
                        'last_purchase_price' =>
                            $line->unitPrice,
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Accounting Mapping
                    |--------------------------------------------------------------------------
                    */

                    $inventoryAccount =
                        $item
                            ->category
                            ->inventoryAccount
                            ->code;

                    /*
                    |--------------------------------------------------------------------------
                    | Journal Amount
                    |--------------------------------------------------------------------------
                    |
                    | GR transaction value:
                    |
                    | qty received x purchase unit price
                    |
                    */

                    $amount =
                        (float) $line->qty
                        *
                        (float) $line->unitPrice;

                    /*
                    |--------------------------------------------------------------------------
                    | Auto Journal Goods Receipt
                    |--------------------------------------------------------------------------
                    |
                    | Dr Inventory
                    | Cr GRNI
                    |
                    | Journal date harus sama dengan receipt date.
                    |
                    */

                    $this
                        ->autoJournalService
                        ->goodsReceipt(
                            inventoryAccount:
                                $inventoryAccount,

                            amount:
                                $amount,

                            referenceId:
                                $gr->id,

                            userId:
                                $dto->createdBy,

                            journalDate:
                                $receiptDate,
                        );
                }

                /*
                |--------------------------------------------------------------------------
                | Audit Log
                |--------------------------------------------------------------------------
                */

                $this
                    ->auditLogService
                    ->log(
                        module:
                            'Goods Receipt',

                        action:
                            'CREATE',

                        referenceType:
                            'GoodsReceipt',

                        referenceId:
                            $gr->id,

                        oldValues:
                            null,

                        newValues: [
                            'gr_no' =>
                                $gr->gr_no,

                            'receipt_date' =>
                                $receiptDate,

                            'warehouse_id' =>
                                $dto->warehouseId,

                            'purchase_order_id' =>
                                $dto->purchaseOrderId,
                        ],
                    );

                /*
                |--------------------------------------------------------------------------
                | Return
                |--------------------------------------------------------------------------
                */

                return $gr->load(
                    'details'
                );
            }
        );
    }
}
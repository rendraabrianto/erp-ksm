<?php

namespace App\Services;

use App\DTO\GoodsReceiptDTO;
use App\DTO\InventoryTransactionDTO;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Repositories\Contracts\GoodsReceiptRepositoryInterface;
use Illuminate\Support\Facades\DB;
use App\Models\Warehouse;

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

                $receiptDate =
                    $dto->receiptDate
                    ?? now()->toDateString();

                /*
                |--------------------------------------------------------------------------
                | Lock Purchase Order
                |--------------------------------------------------------------------------
                */

                $purchaseOrder =
                    PurchaseOrder::query()
                        ->whereKey(
                            $dto->purchaseOrderId
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $warehouse =
                    Warehouse::query()
                        ->whereKey(
                            $dto->warehouseId
                        )
                        ->firstOrFail();

                $companyId =
                    (int) $warehouse->company_id;

                /*
                |--------------------------------------------------------------------------
                | Create Goods Receipt Header
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
                | Collect Journal Lines
                |--------------------------------------------------------------------------
                |
                | Inventory tetap dipost per item karena setiap item menghasilkan
                | stock ledger masing-masing.
                |
                | Journal TIDAK dipost di dalam loop.
                |
                */

                $journalInventoryLines =
                    [];

                /*
                |--------------------------------------------------------------------------
                | Process Goods Receipt Lines
                |--------------------------------------------------------------------------
                */

                foreach (
                    $dto->lines
                    as $line
                ) {

                    /*
                    |--------------------------------------------------------------------------
                    | Deterministic Purchase Order Detail
                    |--------------------------------------------------------------------------
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
                    | Item Consistency
                    |--------------------------------------------------------------------------
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
                    | Remaining Purchase Order Qty
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
                    | Item + Inventory Account
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
                    | Update PO Received Qty
                    |--------------------------------------------------------------------------
                    */

                    $poDetail
                        ->increment(
                            'received_qty',
                            $line->qty
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | Inventory IN
                    |--------------------------------------------------------------------------
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
                    | Last Purchase Price
                    |--------------------------------------------------------------------------
                    |
                    | average_cost tidak diubah di items karena warehouse costing
                    | bersumber dari inventory ledger.
                    |
                    */

                    $item->update([
                        'last_purchase_price' =>
                            $line->unitPrice,
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Collect Accounting Information
                    |--------------------------------------------------------------------------
                    */

                    $inventoryAccount =
                        $item
                            ->category
                            ->inventoryAccount
                            ->code;

                    $amount =
                        (float) $line->qty
                        *
                        (float) $line->unitPrice;

                    $journalInventoryLines[] =
                        [
                            'accountCode' =>
                                $inventoryAccount,

                            'amount' =>
                                $amount,

                            'description' =>
                                'Inventory',
                        ];
                }

                /*
                |--------------------------------------------------------------------------
                | One Journal Per Goods Receipt
                |--------------------------------------------------------------------------
                |
                | Semua detail selesai lebih dahulu.
                |
                | Jika journal gagal, outer DB transaction akan rollback:
                |
                | - GR header
                | - GR detail
                | - PO received_qty
                | - stock ledger
                | - last purchase price
                |
                */

                if (
                        count(
                            $journalInventoryLines
                        ) > 0
                    ) {
                        $this
                            ->autoJournalService
                            ->goodsReceipt(
                                inventoryAccount:
                                    $journalInventoryLines,

                                amount:
                                    null,

                                referenceId:
                                    $gr->id,

                                userId:
                                    $dto->createdBy,

                                companyId:
                                    $companyId,

                                journalDate:
                                    $receiptDate,
                            );
                    }

                /*
                |--------------------------------------------------------------------------
                | Synchronize Purchase Order Status
                |--------------------------------------------------------------------------
                */

                $poDetails =
                    PurchaseOrderDetail::query()
                        ->where(
                            'purchase_order_id',
                            $purchaseOrder->id
                        )
                        ->get();

                $hasReceivedQuantity =
                    $poDetails->contains(
                        function ($detail) {
                            return
                                (float)
                                $detail->received_qty
                                >
                                0;
                        }
                    );

                $allCompleted =
                    $poDetails->isNotEmpty()
                    &&
                    $poDetails->every(
                        function ($detail) {
                            return
                                (float)
                                $detail->received_qty
                                >=
                                (float)
                                $detail->qty;
                        }
                    );

                if (
                    $hasReceivedQuantity
                    &&
                    $allCompleted
                ) {

                    $purchaseOrder->status =
                        'COMPLETED';

                    $purchaseOrder->save();

                } elseif (
                    $hasReceivedQuantity
                ) {

                    $purchaseOrder->status =
                        'PARTIAL';

                    $purchaseOrder->save();
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

                            'purchase_order_status' =>
                                $purchaseOrder->status,
                        ],
                    );

                return $gr->load(
                    'details'
                );
            }
        );
    }
}
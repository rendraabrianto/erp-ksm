<?php

namespace App\Services;

use App\DTO\GoodsReceiptDTO;
use App\DTO\InventoryTransactionDTO;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\Warehouse;
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
        private CompanyGuardService $companyGuardService,
        private AccountingAccountResolverService $accountResolver,
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
                |
                | Purchase Order adalah ownership authority Goods Receipt.
                |
                */

                $purchaseOrder =
                    PurchaseOrder::query()
                        ->whereKey(
                            $dto->purchaseOrderId
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $companyId =
                    (int) $purchaseOrder->company_id;

                /*
                |--------------------------------------------------------------------------
                | Actor Company Guard
                |--------------------------------------------------------------------------
                |
                | Purchase Order is the authoritative company ownership source.
                | createdBy is only the transaction actor.
                |
                */

                $this
                    ->companyGuardService
                    ->assertActorBelongsToCompany(
                        $dto->createdBy,
                        $companyId
                    );

                /*
                |--------------------------------------------------------------------------
                | Resolve Warehouse
                |--------------------------------------------------------------------------
                */

                $warehouse =
                    Warehouse::query()
                        ->whereKey(
                            $dto->warehouseId
                        )
                        ->firstOrFail();

                /*
                |--------------------------------------------------------------------------
                | Company Consistency Guard
                |--------------------------------------------------------------------------
                |
                | Warehouse hanya merupakan destination inventory.
                |
                | Ownership GR tetap berasal dari Purchase Order.
                | Warehouse dari company lain tidak boleh menerima barang
                | untuk Purchase Order milik company tersebut.
                |
                */

                if (
                    (int) $warehouse->company_id
                    !==
                    $companyId
                ) {
                    throw new \RuntimeException(
                        'Goods receipt warehouse does not belong to purchase order company.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Create Goods Receipt Header
                |--------------------------------------------------------------------------
                */

                $gr =
                    $this
                        ->repository
                        ->create([
                            'company_id' =>
                                $companyId,

                            'gr_no' =>
                                $this
                                    ->documentSequenceService
                                    ->next('GR'),

                            'purchase_order_id' =>
                                $purchaseOrder->id,

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
                                $purchaseOrder->id
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
                        (float) $poDetail->qty;

                    $receivedQty =
                        (float) $poDetail->received_qty;

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
                    
                    /*
                    |--------------------------------------------------------------------------
                    | Item Company Ownership Guard
                    |--------------------------------------------------------------------------
                    |
                    | Purchase Order adalah authoritative company ownership source untuk GR.
                    | Item pada setiap Purchase Order Detail wajib dimiliki company yang sama.
                    |
                    */

                    if (
                        (int) $item->company_id
                        !==
                        $companyId
                    ) {
                        throw new \RuntimeException(
                            'Item does not belong to transaction company.'
                        );
                    }

                    if (
                        ! $item->category
                        ||
                        ! $item
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
                    | Company Accounting Guard
                    |--------------------------------------------------------------------------
                    |
                    | Inventory account yang dipetakan pada Item Category
                    | wajib berasal dari company yang sama dengan GR / PO.
                    |
                    */

                    $inventoryAccount =
                        $this
                            ->accountResolver
                            ->accountForCompany(
                                (int) $item
                                    ->category
                                    ->inventory_account_id,

                                $companyId,

                                'Inventory account'
                            );

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
                    */

                    $item->update([
                        'last_purchase_price' =>
                            $line->unitPrice,
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Collect Accounting Information
                    |--------------------------------------------------------------------------
                    |
                    | $inventoryAccount sudah berupa Account model yang telah
                    | lolos company/account guard.
                    |
                    */

                    $amount =
                        (float) $line->qty
                        *
                        (float) $line->unitPrice;

                    $journalInventoryLines[] =
                        [
                            'accountCode' =>
                                $inventoryAccount->code,

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
                | Company journal mengikuti ownership Purchase Order / GR,
                | bukan warehouse dan bukan created_by.
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

                            'company_id' =>
                                $gr->company_id,

                            'receipt_date' =>
                                $receiptDate,

                            'warehouse_id' =>
                                $dto->warehouseId,

                            'purchase_order_id' =>
                                $purchaseOrder->id,

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
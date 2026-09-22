<?php

namespace App\Services;

use App\DTO\DeliveryOrderDTO;
use App\DTO\InventoryTransactionDTO;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Models\Warehouse;
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
        protected CompanyGuardService $companyGuardService,
        private AccountingAccountResolverService $accountResolver,
    ) {
    }

    public function create(
        DeliveryOrderDTO $dto
    ) {
        return DB::transaction(
            function () use ($dto) {

                $deliveryDate =
                    $dto->deliveryDate
                    ?? now()->toDateString();

                /*
                |--------------------------------------------------------------------------
                | Lock Sales Order
                |--------------------------------------------------------------------------
                */

                $salesOrder =
                    SalesOrder::query()
                        ->whereKey(
                            $dto->salesOrderId
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                /*
                |--------------------------------------------------------------------------
                | Company Ownership
                |--------------------------------------------------------------------------
                |
                | Delivery Order wajib mewarisi company dari Sales Order.
                |
                */

                $companyId =
                    (int) $salesOrder->company_id;

                $this
                    ->companyGuardService
                    ->assertActorBelongsToCompany(
                        $dto->createdBy,
                        $companyId
                    );

                /*
                |--------------------------------------------------------------------------
                | Validate Warehouse Company
                |--------------------------------------------------------------------------
                */

                $warehouse =
                    Warehouse::query()
                        ->whereKey(
                            $dto->warehouseId
                        )
                        ->firstOrFail();

                if (
                    (int) $warehouse->company_id
                    !==
                    $companyId
                ) {
                    throw new \RuntimeException(
                        'Delivery order warehouse does not belong to sales order company.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Create Delivery Order Header
                |--------------------------------------------------------------------------
                */

                $do =
                    $this
                        ->repository
                        ->create([
                            'company_id' =>
                                $companyId,

                            'do_no' =>
                                $this
                                    ->documentSequenceService
                                    ->next(
                                        $companyId,
                                        'DO'
                                    ),

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
                | Collect Journal Lines
                |--------------------------------------------------------------------------
                */

                $journalTransactionLines = [];

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

                    /*
                    |--------------------------------------------------------------------------
                    | Item Company Ownership Guard
                    |--------------------------------------------------------------------------
                    |
                    | Sales Order adalah authoritative company ownership source untuk DO.
                    | Item pada setiap Sales Order Detail wajib dimiliki company yang sama.
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

                    if (
                        ! $item
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
                    | Company Accounting Guard
                    |--------------------------------------------------------------------------
                    |
                    | Account mapping pada Item Category wajib berasal dari
                    | company yang sama dengan Delivery Order.
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

                    $cogsAccount =
                        $this
                            ->accountResolver
                            ->accountForCompany(
                                (int) $item
                                    ->category
                                    ->cogs_account_id,

                                $companyId,

                                'COGS account'
                            );

                    /*
                    |--------------------------------------------------------------------------
                    | Validate Remaining SO Quantity
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
                    | Inventory OUT
                    |--------------------------------------------------------------------------
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
                    | DO Detail
                    |--------------------------------------------------------------------------
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
                                $inventoryTransaction
                                    ->unit_cost,

                            'remarks' =>
                                $line->remarks,
                        ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Update Delivered Qty
                    |--------------------------------------------------------------------------
                    */

                    $soDetail
                        ->increment(
                            'delivered_qty',
                            $line->qty
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | Accounting
                    |--------------------------------------------------------------------------
                    |
                    | $inventoryAccount dan $cogsAccount di sini sudah berupa
                    | Account model yang telah lolos company/account guard.
                    |
                    */

                    $amount =
                        (float)
                        $inventoryTransaction
                            ->total_cost;

                    /*
                    |--------------------------------------------------------------------------
                    | Collect Journal Data
                    |--------------------------------------------------------------------------
                    */

                    $journalTransactionLines[] =
                        [
                            'cogsAccount' =>
                                $cogsAccount->code,

                            'inventoryAccount' =>
                                $inventoryAccount->code,

                            'amount' =>
                                $amount,
                        ];
                }

                /*
                |--------------------------------------------------------------------------
                | One Journal Per Delivery Order
                |--------------------------------------------------------------------------
                */

                if (
                    count(
                        $journalTransactionLines
                    ) > 0
                ) {
                    $this
                        ->autoJournalService
                        ->deliveryOrder(
                            cogsAccount:
                                $journalTransactionLines,

                            inventoryAccount:
                                null,

                            amount:
                                null,

                            referenceId:
                                $do->id,

                            userId:
                                $dto->createdBy,

                            journalDate:
                                $deliveryDate,

                            companyId:
                                $companyId,
                        );
                }

                /*
                |--------------------------------------------------------------------------
                | Synchronize Sales Order Status
                |--------------------------------------------------------------------------
                */

                $soDetails =
                    SalesOrderDetail::query()
                        ->where(
                            'sales_order_id',
                            $salesOrder->id
                        )
                        ->get();

                $hasDeliveredQuantity =
                    $soDetails->contains(
                        function ($detail) {
                            return
                                (float)
                                $detail->delivered_qty
                                >
                                0;
                        }
                    );

                $allCompleted =
                    $soDetails->isNotEmpty()
                    &&
                    $soDetails->every(
                        function ($detail) {
                            return
                                (float)
                                $detail->delivered_qty
                                >=
                                (float)
                                $detail->qty;
                        }
                    );

                if (
                    $hasDeliveredQuantity
                    &&
                    $allCompleted
                ) {
                    $salesOrder->status =
                        'COMPLETED';

                    $salesOrder->save();

                } elseif (
                    $hasDeliveredQuantity
                ) {
                    $salesOrder->status =
                        'PARTIAL';

                    $salesOrder->save();
                }

                /*
                |--------------------------------------------------------------------------
                | Audit
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

                            'company_id' =>
                                $companyId,

                            'delivery_date' =>
                                $deliveryDate,

                            'warehouse_id' =>
                                $dto->warehouseId,

                            'sales_order_id' =>
                                $dto->salesOrderId,

                            'sales_order_status' =>
                                $salesOrder->status,
                        ]
                    );

                return $do->load(
                    'details'
                );
            }
        );
    }
}
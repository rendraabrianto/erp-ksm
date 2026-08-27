<?php

namespace App\Services;

use App\DTO\InventoryAdjustmentCreateDTO;
use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentDetail;
use App\Models\Item;
use Illuminate\Support\Facades\DB;

use App\DTO\InventoryTransactionDTO;
use App\DTO\JournalEntryDTO;
use App\DTO\JournalLineDTO;
use App\Models\Account;

class InventoryAdjustmentService
{
    public function __construct(
        private DocumentSequenceService $documentSequenceService,
        private InventoryCostingService $costingService,
        private InventoryTransactionService $inventoryTransactionService,
        private JournalPostingService $journalPostingService,
    ) {}

    public function create(
        InventoryAdjustmentCreateDTO $dto
    ): InventoryAdjustment {

        return DB::transaction(
            function () use ($dto) {

                if (empty($dto->details)) {
                    throw new \RuntimeException(
                        'Inventory adjustment must contain at least one item.'
                    );
                }

                $adjustment =
                    InventoryAdjustment::create([
                        'adjustment_no' =>
                            $this
                                ->documentSequenceService
                                ->next('ADJ'),

                        'adjustment_date' =>
                            $dto->adjustmentDate,

                        'warehouse_id' =>
                            $dto->warehouseId,

                        'status' =>
                            'DRAFT',

                        'reason' =>
                            $dto->reason,

                        'remarks' =>
                            $dto->remarks,

                        'created_by' =>
                            $dto->createdBy,
                    ]);

                foreach ($dto->details as $detail) {

                    $itemId =
                        (int) $detail['item_id'];

                    $physicalQty =
                        (float) $detail['physical_qty'];

                    if ($physicalQty < 0) {
                        throw new \RuntimeException(
                            'Physical quantity cannot be negative.'
                        );
                    }

                    Item::findOrFail(
                        $itemId
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Snapshot hanya untuk tampilan DRAFT
                    |--------------------------------------------------------------------------
                    |
                    | Saat POST nanti angka ini WAJIB dihitung ulang.
                    |
                    */

                    $state =
                        $this->costingService
                            ->getCurrentState(
                                $dto->warehouseId,
                                $itemId
                            );

                    $systemQty =
                        (float) $state['qty'];

                    $adjustmentQty =
                        $physicalQty
                        -
                        $systemQty;

                    $unitCost =
                        (float) $state[
                            'average_cost'
                        ];

                    $totalCost =
                        abs($adjustmentQty)
                        *
                        $unitCost;

                    InventoryAdjustmentDetail::create([
                        'inventory_adjustment_id' =>
                            $adjustment->id,

                        'item_id' =>
                            $itemId,

                        'system_qty' =>
                            round(
                                $systemQty,
                                4
                            ),

                        'physical_qty' =>
                            round(
                                $physicalQty,
                                4
                            ),

                        'adjustment_qty' =>
                            round(
                                $adjustmentQty,
                                4
                            ),

                        'unit_cost' =>
                            round(
                                $unitCost,
                                2
                            ),

                        'total_cost' =>
                            round(
                                $totalCost,
                                2
                            ),

                        'remarks' =>
                            $detail['remarks']
                            ?? null,
                    ]);
                }

                return $adjustment
                    ->load([
                        'warehouse',
                        'details.item',
                    ]);
            }
        );
    }

    public function post(
        int $adjustmentId,
        int $postedBy
    ): InventoryAdjustment {

        return DB::transaction(
            function () use (
                $adjustmentId,
                $postedBy
            ) {

                $adjustment =
                    InventoryAdjustment::query()
                        ->with([
                            'details.item.category',
                        ])
                        ->lockForUpdate()
                        ->findOrFail(
                            $adjustmentId
                        );

                if (
                    $adjustment->status
                    ===
                    'POSTED'
                ) {
                    return $adjustment;
                }

                if (
                    $adjustment->status
                    !==
                    'DRAFT'
                ) {
                    throw new \RuntimeException(
                        'Only DRAFT inventory adjustment can be posted.'
                    );
                }

                foreach (
                    $adjustment->details
                    as $detail
                ) {

                    $item =
                        $detail->item;

                    if (! $item) {
                        throw new \RuntimeException(
                            'Adjustment item not found.'
                        );
                    }

                    $category =
                        $item->category;

                    if (! $category) {
                        throw new \RuntimeException(
                            'Item category not found.'
                        );
                    }

                    if (
                        ! $category
                            ->inventory_account_id
                    ) {
                        throw new \RuntimeException(
                            'Inventory account mapping not found.'
                        );
                    }

                    if (
                        ! $category
                            ->adjustment_gain_account_id
                        ||
                        ! $category
                            ->adjustment_loss_account_id
                    ) {
                        throw new \RuntimeException(
                            'Adjustment gain/loss account mapping not found.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Recalculate current stock at posting time
                    |--------------------------------------------------------------------------
                    */

                    $state =
                        $this->costingService
                            ->getCurrentState(
                                $adjustment->warehouse_id,
                                $detail->item_id
                            );

                    $systemQty =
                        (float)
                        $state['qty'];

                    $averageCost =
                        (float)
                        $state['average_cost'];

                    $physicalQty =
                        (float)
                        $detail->physical_qty;

                    $adjustmentQty =
                        $physicalQty
                        -
                        $systemQty;

                    /*
                    |--------------------------------------------------------------------------
                    | No difference
                    |--------------------------------------------------------------------------
                    */

                    if (
                        abs($adjustmentQty)
                        <
                        0.000001
                    ) {

                        $detail->update([
                            'system_qty' =>
                                round(
                                    $systemQty,
                                    4
                                ),

                            'adjustment_qty' =>
                                0,

                            'unit_cost' =>
                                round(
                                    $averageCost,
                                    2
                                ),

                            'total_cost' =>
                                0,
                        ]);

                        continue;
                    }

                    $qtyIn = 0.0;
                    $qtyOut = 0.0;

                    if ($adjustmentQty > 0) {
                        $qtyIn =
                            $adjustmentQty;
                    } else {
                        $qtyOut =
                            abs(
                                $adjustmentQty
                            );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Post inventory movement
                    |--------------------------------------------------------------------------
                    */

                    $ledger =
                        $this
                            ->inventoryTransactionService
                            ->post(
                                new InventoryTransactionDTO(
                                    warehouseId:
                                        (int)
                                        $adjustment->warehouse_id,

                                    itemId:
                                        (int)
                                        $detail->item_id,

                                    referenceType:
                                        'INVENTORY_ADJUSTMENT',

                                    referenceId:
                                        (int)
                                        $adjustment->id,

                                    qtyIn:
                                        $qtyIn,

                                    qtyOut:
                                        $qtyOut,

                                    unitCost:
                                        $averageCost,

                                    remarks:
                                        'Inventory Adjustment '
                                        .
                                        $adjustment
                                            ->adjustment_no,
                                )
                            );

                    $postedUnitCost =
                        (float)
                        $ledger->unit_cost;

                    $postedTotalCost =
                        (float)
                        $ledger->total_cost;

                    /*
                    |--------------------------------------------------------------------------
                    | Update authoritative adjustment detail
                    |--------------------------------------------------------------------------
                    */

                    $detail->update([
                        'system_qty' =>
                            round(
                                $systemQty,
                                4
                            ),

                        'adjustment_qty' =>
                            round(
                                $adjustmentQty,
                                4
                            ),

                        'unit_cost' =>
                            round(
                                $postedUnitCost,
                                2
                            ),

                        'total_cost' =>
                            round(
                                $postedTotalCost,
                                2
                            ),
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Resolve accounts
                    |--------------------------------------------------------------------------
                    */

                    $inventoryAccount =
                        Account::findOrFail(
                            $category
                                ->inventory_account_id
                        );

                    if ($adjustmentQty > 0) {

                        $offsetAccount =
                            Account::findOrFail(
                                $category
                                    ->adjustment_gain_account_id
                            );

                        $lines = [
                            new JournalLineDTO(
                                accountCode:
                                    $inventoryAccount->code,

                                quantity:
                                    $qtyIn,

                                unitPrice:
                                    $postedUnitCost,

                                debit:
                                    $postedTotalCost,

                                credit:
                                    0,

                                description:
                                    'Inventory Adjustment IN',
                            ),

                            new JournalLineDTO(
                                accountCode:
                                    $offsetAccount->code,

                                quantity:
                                    0,

                                unitPrice:
                                    0,

                                debit:
                                    0,

                                credit:
                                    $postedTotalCost,

                                description:
                                    'Inventory Adjustment Gain',
                            ),
                        ];
                    } else {

                        $offsetAccount =
                            Account::findOrFail(
                                $category
                                    ->adjustment_loss_account_id
                            );

                        $lines = [
                            new JournalLineDTO(
                                accountCode:
                                    $offsetAccount->code,

                                quantity:
                                    0,

                                unitPrice:
                                    0,

                                debit:
                                    $postedTotalCost,

                                credit:
                                    0,

                                description:
                                    'Inventory Adjustment Loss',
                            ),

                            new JournalLineDTO(
                                accountCode:
                                    $inventoryAccount->code,

                                quantity:
                                    $qtyOut,

                                unitPrice:
                                    $postedUnitCost,

                                debit:
                                    0,

                                credit:
                                    $postedTotalCost,

                                description:
                                    'Inventory Adjustment OUT',
                            ),
                        ];
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Post journal
                    |--------------------------------------------------------------------------
                    */

                    $this
                        ->journalPostingService
                        ->post(
                            new JournalEntryDTO(
                                referenceType:
                                    'INVENTORY_ADJUSTMENT',

                                referenceId:
                                    (int)
                                    $adjustment->id,

                                description:
                                    'Inventory Adjustment '
                                    .
                                    $adjustment
                                        ->adjustment_no,

                                createdBy:
                                    $postedBy,

                                lines:
                                    $lines,

                                journalDate:
                                    $adjustment
                                        ->adjustment_date
                                        ->format(
                                            'Y-m-d'
                                        ),

                                journalPurpose:
                                    'NORMAL',

                                sourceJournalId:
                                    null,

                                reconciliationKey:
                                    null,
                            )
                        );
                }

                /*
                |--------------------------------------------------------------------------
                | Mark POSTED
                |--------------------------------------------------------------------------
                */

                $adjustment->update([
                    'status' =>
                        'POSTED',

                    'posted_by' =>
                        $postedBy,

                    'posted_at' =>
                        now(),
                ]);

                return $adjustment
                    ->fresh([
                        'warehouse',
                        'details.item',
                        'poster',
                    ]);
            }
        );
    }
}
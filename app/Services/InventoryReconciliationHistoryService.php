<?php

namespace App\Services;

use App\DTO\InventoryHistoricalReconciliationDTO;
use App\DTO\InventoryReconciliationAdjustmentDTO;
use App\Models\InventoryReconciliationHistory;
use Illuminate\Support\Facades\DB;

class InventoryReconciliationHistoryService
{
    public function __construct(
        private InventoryHistoricalReconciliationService $historicalService,
        private InventoryReconciliationAdjustmentService $adjustmentService,
    ) {}

    public function applyAndRecord(
        InventoryReconciliationAdjustmentDTO $adjustmentDto,
        int $executedBy
    ): array {

        return DB::transaction(
            function () use (
                $adjustmentDto,
                $executedBy
            ) {

                $historicalDto =
                    new InventoryHistoricalReconciliationDTO(
                        warehouseId: $adjustmentDto->warehouseId,
                        itemId: $adjustmentDto->itemId,
                        dateFrom: $adjustmentDto->dateFrom,
                        dateTo: $adjustmentDto->dateTo,
                        inventoryAccountId: $adjustmentDto->inventoryAccountId,
                    );

                $before =
                    $this->historicalService
                        ->reconcile($historicalDto);

                $apply =
                    $this->adjustmentService
                        ->apply(
                            $adjustmentDto,
                            $executedBy
                        );

                if (
                    $apply['status']
                    ===
                    'NOTHING_TO_POST'
                ) {
                    return [
                        'apply' => $apply,
                        'before' => $before,
                        'after' => $before,
                        'history' => null,
                    ];
                }

                $after =
                    $this->historicalService
                        ->reconcile($historicalDto);

                $journals =
                    collect(
                        $apply['journals']
                    );

                $history =
                    InventoryReconciliationHistory::create([
                        'warehouse_id' =>
                            $adjustmentDto->warehouseId,

                        'item_id' =>
                            $adjustmentDto->itemId,

                        'date_from' =>
                            $adjustmentDto->dateFrom,

                        'date_to' =>
                            $adjustmentDto->dateTo,

                        'missing_before' =>
                            $before['missing_journal_count'],

                        'mismatch_before' =>
                            $before['cost_mismatch_count'],

                        'orphan_before' =>
                            $before['orphan_journal_count'],

                        'recovery_posted' =>
                            $journals
                                ->where(
                                    'journal_purpose',
                                    'RECOVERY'
                                )
                                ->count(),

                        'correction_posted' =>
                            $journals
                                ->where(
                                    'journal_purpose',
                                    'COST_CORRECTION'
                                )
                                ->count(),

                        'reversal_posted' =>
                            $journals
                                ->where(
                                    'journal_purpose',
                                    'REVERSAL'
                                )
                                ->count(),

                        'journal_posted_count' =>
                            $apply['posted_count'],

                        'missing_after' =>
                            $after['missing_journal_count'],

                        'mismatch_after' =>
                            $after['cost_mismatch_count'],

                        'orphan_after' =>
                            $after['orphan_journal_count'],

                        'is_reconciled_after' =>
                            $after['is_reconciled'],

                        'executed_by' =>
                            $executedBy,

                        'executed_at' =>
                            now(),
                    ]);

                return [
                    'apply' => $apply,
                    'before' => $before,
                    'after' => $after,
                    'history' => $history,
                ];
            }
        );
    }
}
<?php

namespace App\Repositories\Eloquent;

use App\DTO\InventoryReconciliationFilterDTO;
use App\Models\JournalDetail;
use App\Models\StockLedger;
use App\Repositories\Contracts\InventoryReconciliationRepositoryInterface;

class InventoryReconciliationRepository
implements InventoryReconciliationRepositoryInterface
{
    public function getStockLedgerState(
        InventoryReconciliationFilterDTO $dto
    ) {
        return StockLedger::query()

            ->where(
                'warehouse_id',
                $dto->warehouseId
            )

            ->where(
                'item_id',
                $dto->itemId
            )

            ->whereDate(
                'transaction_date',
                '<=',
                $dto->dateTo
            )

            ->orderByDesc('id')

            ->first();
    }

    public function getGlInventoryBalance(
        InventoryReconciliationFilterDTO $dto
    ) {
        return JournalDetail::query()

            ->selectRaw('
                SUM(debit) AS total_debit,
                SUM(credit) AS total_credit
            ')

            ->where(
                'account_id',
                $dto->inventoryAccountId
            )

            ->whereHas(
                'journal',
                function ($q) use ($dto) {

                    $q->whereDate(
                        'journal_date',
                        '<=',
                        $dto->dateTo
                    );
                }
            )

            ->first();
    }
}
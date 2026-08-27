<?php

namespace App\Repositories\Eloquent;

use App\Models\StockLedger;
use App\Repositories\Contracts\StockLedgerRepositoryInterface;

class StockLedgerRepository
implements StockLedgerRepositoryInterface
{
    public function create(
        array $data
    ): StockLedger
    {
        return StockLedger::create($data);
    }

    public function latestBalance(
        int $warehouseId,
        int $itemId
    ): float
    {
        return (float)
            StockLedger::where(
                'warehouse_id',
                $warehouseId
            )
            ->where(
                'item_id',
                $itemId
            )
            ->latest('id')
            ->value('balance_qty')
            ?? 0;
    }
}
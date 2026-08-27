<?php

namespace App\Repositories\Eloquent;

use App\DTO\CurrentStockFilterDTO;
use App\Models\StockLedger;
use App\Repositories\Contracts\CurrentStockRepositoryInterface;

class CurrentStockRepository implements
    CurrentStockRepositoryInterface
{
    public function getCurrentStocks(
        CurrentStockFilterDTO $dto
    ) {
        $query =
            StockLedger::query()
                ->with([
                    'warehouse',
                    'item',
                ]);

        if ($dto->warehouseId !== null) {
            $query->where(
                'warehouse_id',
                $dto->warehouseId
            );
        }

        if ($dto->itemId !== null) {
            $query->where(
                'item_id',
                $dto->itemId
            );
        }

        if ($dto->asOfDate !== null) {
            $query->whereDate(
                'transaction_date',
                '<=',
                $dto->asOfDate
            );
        }

        return $query
            ->orderBy('warehouse_id')
            ->orderBy('item_id')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();
    }
}
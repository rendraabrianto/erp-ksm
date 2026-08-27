<?php

namespace App\Repositories\Contracts;

use App\Models\StockLedger;

interface StockLedgerRepositoryInterface
{
    public function create(
        array $data
    ): StockLedger;

    public function latestBalance(
        int $warehouseId,
        int $itemId
    ): float;
}
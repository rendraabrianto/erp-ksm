<?php

namespace App\Repositories\Contracts;

use App\DTO\InventoryReconciliationFilterDTO;

interface InventoryReconciliationRepositoryInterface
{
    public function getStockLedgerState(
        InventoryReconciliationFilterDTO $dto
    );

    public function getGlInventoryBalance(
        InventoryReconciliationFilterDTO $dto
    );
}
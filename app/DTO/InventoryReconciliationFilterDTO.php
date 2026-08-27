<?php

namespace App\DTO;

class InventoryReconciliationFilterDTO
{
    public function __construct(

        public int $warehouseId,
        public int $itemId,
        public string $dateTo,
        public int $inventoryAccountId,

    ) {}
}
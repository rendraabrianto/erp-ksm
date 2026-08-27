<?php

namespace App\DTO;

class InventoryHistoricalReconciliationDTO
{
    public function __construct(

        public int $warehouseId,
        public int $itemId,
        public string $dateFrom,
        public string $dateTo,
        public int $inventoryAccountId,

    ) {}
}
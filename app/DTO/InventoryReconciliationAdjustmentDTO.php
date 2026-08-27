<?php

namespace App\DTO;

class InventoryReconciliationAdjustmentDTO
{
    public function __construct(
        public int $warehouseId,
        public int $itemId,
        public string $dateFrom,
        public string $dateTo,
        public int $inventoryAccountId,
        public int $cogsAccountId,
        public int $grniAccountId,
    ) {}
}
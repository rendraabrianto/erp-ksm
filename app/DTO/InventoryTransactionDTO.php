<?php

namespace App\DTO;

class InventoryTransactionDTO
{
    public function __construct(
        public int $warehouseId,
        public int $itemId,
        public string $referenceType,
        public int $referenceId,
        public float $qtyIn,
        public float $qtyOut,
        public float $unitCost,
        public ?string $remarks = null,
    ) {}
}
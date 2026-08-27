<?php

namespace App\DTO;

class InventoryValuationFilterDTO
{
    public function __construct(
        public ?int $warehouseId = null,
        public ?int $itemId = null,
        public ?string $asOfDate = null,
    ) {}
}
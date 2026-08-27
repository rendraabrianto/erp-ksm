<?php

namespace App\DTO;

class CurrentStockFilterDTO
{
    public function __construct(
        public ?int $warehouseId = null,
        public ?int $itemId = null,
        public ?string $asOfDate = null,
    ) {}
}
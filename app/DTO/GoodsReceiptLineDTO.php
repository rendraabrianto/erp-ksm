<?php

namespace App\DTO;

class GoodsReceiptLineDTO
{
    public function __construct(
        public int $itemId,
        public float $qty,
        public float $unitPrice,
        public ?string $remarks = null,
    ) {}
}
<?php

namespace App\DTO;

class PurchaseRequestLineDTO
{
    public function __construct(
        public int $itemId,
        public float $qty,
        public ?string $remarks = null,
    ) {}
}
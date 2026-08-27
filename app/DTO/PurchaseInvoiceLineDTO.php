<?php

namespace App\DTO;

class PurchaseInvoiceLineDTO
{
    public function __construct(
        public int $itemId,
        public float $qty,
        public float $unitPrice,
        public float $amount,
        public ?string $remarks = null,
    ) {}
}
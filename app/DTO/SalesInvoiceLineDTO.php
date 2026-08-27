<?php

namespace App\DTO;

class SalesInvoiceLineDTO
{
    public function __construct(

        public int $itemId,
        public float $qty,
        public float $unitPrice,
        public float $discount = 0,
        public ?string $remarks = null,

    ) {}
}
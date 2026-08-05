<?php

namespace App\DTO;

class JournalLineDTO
{
    public function __construct(
        public string $accountCode,
        public float $quantity,
        public float $unitPrice,
        public float $debit,
        public float $credit,
        public ?string $description = null,
    ) {}
}
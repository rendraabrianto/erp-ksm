<?php

namespace App\DTO;

class AccountPayableDTO
{
    public function __construct(
        public int $companyId,
        public string $referenceType,
        public int $referenceId,
        public string $supplierName,
        public string $invoiceDate,
        public string $dueDate,
        public float $amount,
    ) {}
}
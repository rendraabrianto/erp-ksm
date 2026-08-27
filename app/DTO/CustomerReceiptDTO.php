<?php

namespace App\DTO;

class CustomerReceiptDTO
{
    public function __construct(
        public int $customerId,
        public int $accountReceivableId,
        public int $cashBankAccountId,
        public float $amount,
        public string $remarks,
        public int $createdBy,
    ) {}
}
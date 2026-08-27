<?php

namespace App\DTO;

class PaymentVoucherDTO
{
    public function __construct(

        public int $accountPayableId,
        public int $cashBankAccountId,
        public float $amount,
        public string $paymentMethod,
        public ?string $remarks,
        public int $createdBy,

    ) {}
}
<?php

namespace App\DTO;

class SalesInvoiceDTO
{
    public function __construct(

        public int $customerId,
        public int $deliveryOrderId,
        public string $dueDate,
        public string $remarks,
        public int $createdBy,
        public array $lines = [],

    ) {}
}
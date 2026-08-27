<?php

namespace App\DTO;

class SalesOrderDTO
{
    public function __construct(

        public int $customerId,

        public ?string $deliveryDate,

        public ?string $remarks,

        public int $createdBy,

        public array $lines = [],
    ) {}
}
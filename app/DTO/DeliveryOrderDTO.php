<?php

namespace App\DTO;

class DeliveryOrderDTO
{
    public function __construct(

        public int $salesOrderId,
        public string $remarks,
        public int $warehouseId,
        public int $createdBy,
        public array $lines = [],

    ) {}
}
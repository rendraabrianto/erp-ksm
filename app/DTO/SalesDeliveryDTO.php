<?php

namespace App\DTO;

class SalesDeliveryDTO
{
    public function __construct(

        public int $salesOrderId,

        public int $warehouseId,

        public ?string $remarks,

        public int $createdBy,

        public array $lines = [],
    ) {}
}
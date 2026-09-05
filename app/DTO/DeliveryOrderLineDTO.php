<?php

namespace App\DTO;

class DeliveryOrderLineDTO
{
    public function __construct(
        public int $itemId,
        public float $qty,
        public int $salesOrderDetailId,
        public ?string $remarks = null,
    ) {}
}
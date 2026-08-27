<?php

namespace App\DTO;

class DeliveryOrderLineDTO
{
    public function __construct(

        public int $itemId,
        public float $qty,
        public ?string $remarks = null,

    ) {}
}
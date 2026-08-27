<?php

namespace App\DTO;

class SalesDeliveryLineDTO
{
    public function __construct(

        public int $itemId,

        public float $qty,

        public ?string $remarks = null,
    ) {}
}
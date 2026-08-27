<?php

namespace App\DTO;

class ARAgingFilterDTO
{
    public function __construct(
        public string $asOfDate,
        public ?int $customerId = null
    ) {}
}
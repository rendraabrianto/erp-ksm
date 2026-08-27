<?php

namespace App\DTO;

class APAgingFilterDTO
{
    public function __construct(

        public string $asOfDate,

        public ?string $supplierName = null,

    ) {}
}
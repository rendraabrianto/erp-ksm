<?php

namespace App\DTO;

class CashFlowFilterDTO
{
    public function __construct(

        public string $dateFrom,

        public string $dateTo,

    ) {}
}
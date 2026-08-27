<?php

namespace App\DTO;

class BalanceSheetFilterDTO
{
    public function __construct(

        public string $dateFrom,

        public string $dateTo,

    ) {}
}
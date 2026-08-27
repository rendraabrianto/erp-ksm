<?php

namespace App\DTO;

class ProfitLossFilterDTO
{
    public function __construct(

        public string $dateFrom,

        public string $dateTo,

    ) {}
}
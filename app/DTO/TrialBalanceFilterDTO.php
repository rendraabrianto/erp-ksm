<?php

namespace App\DTO;

class TrialBalanceFilterDTO
{
    public function __construct(

        public string $dateFrom,

        public string $dateTo,
    ) {}
}
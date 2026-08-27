<?php

namespace App\DTO;

class FinanceDashboardFilterDTO
{
    public function __construct(

        public string $dateFrom,

        public string $dateTo,

    ) {}
}
<?php

namespace App\DTO;

class GeneralLedgerFilterDTO
{
    public function __construct(

        public string $dateFrom,
        public string $dateTo,
        public ?int $accountId = null,

    ) {}
}
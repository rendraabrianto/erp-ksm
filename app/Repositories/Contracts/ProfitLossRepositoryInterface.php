<?php

namespace App\Repositories\Contracts;

interface ProfitLossRepositoryInterface
{
    public function getProfitLoss(
        string $dateFrom,
        string $dateTo
    );
}
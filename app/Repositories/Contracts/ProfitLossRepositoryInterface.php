<?php

namespace App\Repositories\Contracts;

interface ProfitLossRepositoryInterface
{
    public function getProfitLoss(
        int $companyId,
        string $dateFrom,
        string $dateTo
    );
}
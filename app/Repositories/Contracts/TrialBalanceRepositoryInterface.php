<?php

namespace App\Repositories\Contracts;

interface TrialBalanceRepositoryInterface
{
    public function getTrialBalance(
        int $companyId,
        string $dateFrom,
        string $dateTo
    );
}
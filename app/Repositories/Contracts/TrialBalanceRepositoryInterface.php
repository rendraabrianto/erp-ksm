<?php

namespace App\Repositories\Contracts;

interface TrialBalanceRepositoryInterface
{
    public function getTrialBalance(
        string $dateFrom,
        string $dateTo
    );
}
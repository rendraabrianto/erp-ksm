<?php

namespace App\Services;

use App\DTO\TrialBalanceFilterDTO;
use App\Repositories\Contracts\TrialBalanceRepositoryInterface;

class TrialBalanceService
{
    public function __construct(
        private TrialBalanceRepositoryInterface $repository
    ) {}

    public function getReport(
        int $companyId,
        TrialBalanceFilterDTO $dto
    ) {
        return $this->repository
            ->getTrialBalance(
                companyId:
                    $companyId,

                dateFrom:
                    $dto->dateFrom,

                dateTo:
                    $dto->dateTo
            );
    }
}
<?php

namespace App\Repositories\Contracts;

use App\DTO\FinanceDashboardFilterDTO;

interface FinanceDashboardRepositoryInterface
{
    public function getCashPosition(
        int $companyId,
        FinanceDashboardFilterDTO $dto
    );

    public function getLowStockItems(
        int $companyId
    );

    public function getBankNegativeAccounts(
        int $companyId,
        FinanceDashboardFilterDTO $dto
    );
}
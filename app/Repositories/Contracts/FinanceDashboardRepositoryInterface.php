<?php

namespace App\Repositories\Contracts;

use App\DTO\FinanceDashboardFilterDTO;

interface FinanceDashboardRepositoryInterface
{
    public function getCashPosition(
        FinanceDashboardFilterDTO $dto
    );

    public function getLowStockItems();

    public function getBankNegativeAccounts(
        FinanceDashboardFilterDTO $dto
    );
}
<?php

namespace App\Repositories\Contracts;

use App\DTO\BalanceSheetFilterDTO;

interface BalanceSheetRepositoryInterface
{
    public function getBalances(
        int $companyId,
        BalanceSheetFilterDTO $dto
    );
}
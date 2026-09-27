<?php

namespace App\Repositories\Contracts;

use App\DTO\CashFlowFilterDTO;

interface CashFlowRepositoryInterface
{
    public function getCashTransactions(
        int $companyId,
        CashFlowFilterDTO $dto
    );
}
<?php

namespace App\Repositories\Contracts;

use App\DTO\CashFlowFilterDTO;

interface CashFlowRepositoryInterface
{
    public function getCashTransactions(
        CashFlowFilterDTO $dto
    );
}
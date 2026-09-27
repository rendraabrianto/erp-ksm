<?php

namespace App\Repositories\Contracts;

use App\DTO\CurrentStockFilterDTO;

interface CurrentStockRepositoryInterface
{
    public function getCurrentStocks(
        int $companyId,
        CurrentStockFilterDTO $dto
    );
}
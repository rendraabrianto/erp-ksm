<?php

namespace App\Repositories\Contracts;

use App\DTO\APAgingFilterDTO;

interface APAgingRepositoryInterface
{
    public function getAging(
        APAgingFilterDTO $dto
    );
}
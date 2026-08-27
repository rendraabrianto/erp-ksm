<?php

namespace App\Repositories\Contracts;

use App\DTO\ARAgingFilterDTO;

interface ARAgingRepositoryInterface
{
    public function getAging(
        ARAgingFilterDTO $dto
    );
}
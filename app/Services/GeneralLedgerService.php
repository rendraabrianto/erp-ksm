<?php

namespace App\Services;

use App\DTO\GeneralLedgerFilterDTO;

use App\Repositories\Contracts\GeneralLedgerRepositoryInterface;

class GeneralLedgerService
{
    public function __construct(

        private GeneralLedgerRepositoryInterface $repository

    ) {}

    public function getLedger(
        GeneralLedgerFilterDTO $dto
    )
    {
        return $this->repository
            ->getLedger($dto);
    }
}
<?php

namespace App\Repositories\Contracts;

use App\DTO\GeneralLedgerFilterDTO;

interface GeneralLedgerRepositoryInterface
{
    public function getLedger(
        GeneralLedgerFilterDTO $dto
    );
}
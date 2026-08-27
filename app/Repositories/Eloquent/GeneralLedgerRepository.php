<?php

namespace App\Repositories\Eloquent;

use App\DTO\GeneralLedgerFilterDTO;

use App\Models\JournalDetail;

use App\Repositories\Contracts\GeneralLedgerRepositoryInterface;

class GeneralLedgerRepository
implements GeneralLedgerRepositoryInterface
{
    public function getLedger(
        GeneralLedgerFilterDTO $dto
    )
    {
        return JournalDetail::query()

            ->with([
                'journal',
                'account'
            ])

            ->when(
                $dto->accountId,
                fn ($q) =>
                $q->where(
                    'account_id',
                    $dto->accountId
                )
            )

            ->whereHas(
                'journal',
                function ($q) use ($dto) {

                    $q->whereBetween(
                        'journal_date',
                        [
                            $dto->dateFrom,
                            $dto->dateTo
                        ]
                    );
                }
            )

            ->orderBy('journal_id')

            ->get();
    }
}
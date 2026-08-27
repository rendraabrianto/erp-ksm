<?php

namespace App\Repositories\Eloquent;

use App\DTO\CashFlowFilterDTO;
use App\Models\JournalDetail;
use App\Repositories\Contracts\CashFlowRepositoryInterface;

class CashFlowRepository
implements CashFlowRepositoryInterface
{
    public function getCashTransactions(
        CashFlowFilterDTO $dto
    ) {
        return JournalDetail::query()

            ->with([
                'journal',
                'account'
            ])

            ->whereHas(
                'account',
                function ($q) {

                    $q->whereBetween(
                        'code',
                        [
                            '1001',
                            '1099'
                        ]
                    );
                }
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

            ->where(function ($q) {

                $q->where(
                    'debit',
                    '>',
                    0
                )

                ->orWhere(
                    'credit',
                    '>',
                    0
                );
            })

            ->orderBy(
                'journal_id'
            )

            ->get();
    }
}
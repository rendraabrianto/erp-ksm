<?php

namespace App\Repositories\Eloquent;

use App\DTO\BalanceSheetFilterDTO;
use App\Models\JournalDetail;
use App\Repositories\Contracts\BalanceSheetRepositoryInterface;

class BalanceSheetRepository
implements BalanceSheetRepositoryInterface
{
    public function getBalances(
        BalanceSheetFilterDTO $dto
    ) {
        return JournalDetail::query()

            ->selectRaw('
                account_id,
                SUM(debit) AS total_debit,
                SUM(credit) AS total_credit
            ')

            ->with('account')

            ->whereHas(
                'journal',
                function ($q) use ($dto) {

                    $q->where(
                        'journal_date',
                        '<=',
                        $dto->dateTo
                    );
                }
            )

            ->whereHas(
                'account',
                function ($q) {

                    $q->where(function ($q) {

                        // Asset
                        $q->whereBetween(
                            'code',
                            [
                                '1000',
                                '1999'
                            ]
                        )

                        // Liability
                        ->orWhereBetween(
                            'code',
                            [
                                '2000',
                                '2999'
                            ]
                        )

                        // Equity
                        ->orWhereBetween(
                            'code',
                            [
                                '3000',
                                '3999'
                            ]
                        );
                    });
                }
            )

            ->groupBy('account_id')

            ->orderBy('account_id')

            ->get();
    }
}
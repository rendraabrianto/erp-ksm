<?php

namespace App\Repositories\Eloquent;

use App\Models\JournalDetail;
use App\Repositories\Contracts\ProfitLossRepositoryInterface;

class ProfitLossRepository implements
    ProfitLossRepositoryInterface
{
    public function getProfitLoss(
        int $companyId,
        string $dateFrom,
        string $dateTo
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
                function ($q) use (
                    $companyId,
                    $dateFrom,
                    $dateTo
                ) {
                    $q->where(
                        'company_id',
                        $companyId
                    );

                    $q->whereBetween(
                        'journal_date',
                        [
                            $dateFrom,
                            $dateTo,
                        ]
                    );
                }
            )

            ->whereHas(
                'account',
                function ($q) use (
                    $companyId
                ) {
                    $q->where(
                        'company_id',
                        $companyId
                    );

                    $q->where(function ($q) {

                        $q->whereBetween(
                            'code',
                            [
                                '4000',
                                '4999',
                            ]
                        )

                        ->orWhereBetween(
                            'code',
                            [
                                '5000',
                                '5999',
                            ]
                        )

                        ->orWhereBetween(
                            'code',
                            [
                                '6000',
                                '6999',
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
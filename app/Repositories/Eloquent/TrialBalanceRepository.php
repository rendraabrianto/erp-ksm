<?php

namespace App\Repositories\Eloquent;

use App\Models\JournalDetail;
use App\Repositories\Contracts\TrialBalanceRepositoryInterface;

class TrialBalanceRepository implements
    TrialBalanceRepositoryInterface
{
    public function getTrialBalance(
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
                }
            )

            ->groupBy('account_id')

            ->orderBy('account_id')

            ->get()

            ->map(function ($row) {

                $debit =
                    (float) $row->total_debit;

                $credit =
                    (float) $row->total_credit;

                $saldo =
                    $debit - $credit;

                $row->saldo_debit =
                    $saldo > 0
                        ? $saldo
                        : 0;

                $row->saldo_credit =
                    $saldo < 0
                        ? abs($saldo)
                        : 0;

                return $row;
            });
    }
}
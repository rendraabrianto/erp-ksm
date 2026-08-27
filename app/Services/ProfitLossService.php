<?php

namespace App\Services;

use App\DTO\ProfitLossFilterDTO;
use App\Repositories\Contracts\ProfitLossRepositoryInterface;

class ProfitLossService
{
    public function __construct(

        private ProfitLossRepositoryInterface $repository

    ) {}

    public function getReport(
        ProfitLossFilterDTO $dto
    ) {
        $rows =
            $this->repository->getProfitLoss(
                $dto->dateFrom,
                $dto->dateTo
            );

        $revenue = 0;
        $cogs = 0;
        $expense = 0;

        foreach ($rows as $row) {

            $code =
                $row->account->code;

            $debit =
                (float) $row->total_debit;

            $credit =
                (float) $row->total_credit;

            if (
                str_starts_with($code, '4')
            ) {
                $revenue +=
                    $credit - $debit;
            }

            elseif (
                str_starts_with($code, '5')
            ) {
                $cogs +=
                    $debit - $credit;
            }

            elseif (
                str_starts_with($code, '6')
            ) {
                $expense +=
                    $debit - $credit;
            }
        }

        return [

            'accounts' =>
                $rows,

            'revenue' =>
                $revenue,

            'cogs' =>
                $cogs,

            'gross_profit' =>
                $revenue - $cogs,

            'expense' =>
                $expense,

            'net_profit' =>
                $revenue
                -
                $cogs
                -
                $expense,
        ];
    }
}
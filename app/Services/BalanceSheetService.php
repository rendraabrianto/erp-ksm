<?php

namespace App\Services;

use App\DTO\BalanceSheetFilterDTO;
use App\Repositories\Contracts\BalanceSheetRepositoryInterface;

class BalanceSheetService
{
    public function __construct(

        private BalanceSheetRepositoryInterface $repository,

        private ProfitLossService $profitLossService,

    ) {}

    public function getReport(
        BalanceSheetFilterDTO $dto
    ) {
        $rows =
            $this->repository
                ->getBalances($dto);

        $assets = [];

        $liabilities = [];

        $equity = [];

        $totalAssets = 0;

        $totalLiabilities = 0;

        $totalEquity = 0;

        foreach ($rows as $row) {

            $code =
                $row->account->code;

            $debit =
                (float) $row->total_debit;

            $credit =
                (float) $row->total_credit;

            $balance =
                $debit - $credit;

            /*
            |--------------------------------------------------------------------------
            | ASSET
            |--------------------------------------------------------------------------
            */

            if (
                str_starts_with($code, '1')
            ) {

                $amount =
                    $balance;

                $assets[] = [

                    'account_id' =>
                        $row->account_id,

                    'code' =>
                        $row->account->code,

                    'name' =>
                        $row->account->name,

                    'amount' =>
                        $amount,

                ];

                $totalAssets +=
                    $amount;

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | LIABILITY
            |--------------------------------------------------------------------------
            */

            if (
                str_starts_with($code, '2')
            ) {

                $amount =
                    $credit - $debit;

                $liabilities[] = [

                    'account_id' =>
                        $row->account_id,

                    'code' =>
                        $row->account->code,

                    'name' =>
                        $row->account->name,

                    'amount' =>
                        $amount,

                ];

                $totalLiabilities +=
                    $amount;

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | EQUITY
            |--------------------------------------------------------------------------
            */

            if (
                str_starts_with($code, '3')
            ) {

                $amount =
                    $credit - $debit;

                $equity[] = [

                    'account_id' =>
                        $row->account_id,

                    'code' =>
                        $row->account->code,

                    'name' =>
                        $row->account->name,

                    'amount' =>
                        $amount,

                ];

                $totalEquity +=
                    $amount;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | CURRENT PERIOD PROFIT
        |--------------------------------------------------------------------------
        */

        $profitLoss =
            $this->profitLossService
                ->getReport(
                    new \App\DTO\ProfitLossFilterDTO(

                        dateFrom :
                            $dto->dateFrom,

                        dateTo :
                            $dto->dateTo
                    )
                );

        $currentProfit =
            (float) $profitLoss['net_profit'];

        /*
        |--------------------------------------------------------------------------
        | ADD CURRENT PROFIT TO EQUITY
        |--------------------------------------------------------------------------
        */

        $equity[] = [

            'account_id' =>
                null,

            'code' =>
                'CURRENT_PROFIT',

            'name' =>
                'Laba Tahun Berjalan',

            'amount' =>
                $currentProfit,
        ];

        $totalEquity +=
            $currentProfit;

        /*
        |--------------------------------------------------------------------------
        | TOTAL LIABILITY + EQUITY
        |--------------------------------------------------------------------------
        */

        $totalLiabilitiesAndEquity =
            $totalLiabilities
            +
            $totalEquity;

        /*
        |--------------------------------------------------------------------------
        | BALANCE CHECK
        |--------------------------------------------------------------------------
        */

        $difference =
            $totalAssets
            -
            $totalLiabilitiesAndEquity;

        return [

            'date_from' =>
                $dto->dateFrom,

            'date_to' =>
                $dto->dateTo,

            'assets' =>
                $assets,

            'total_assets' =>
                $totalAssets,

            'liabilities' =>
                $liabilities,

            'total_liabilities' =>
                $totalLiabilities,

            'equity' =>
                $equity,

            'total_equity' =>
                $totalEquity,

            'total_liabilities_and_equity' =>
                $totalLiabilitiesAndEquity,

            'difference' =>
                $difference,

            'is_balanced' =>
                abs($difference) < 0.01,
        ];
    }
}
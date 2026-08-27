<?php

namespace App\Services;

use App\DTO\ARAgingFilterDTO;
use App\DTO\APAgingFilterDTO;
use App\DTO\FinanceDashboardFilterDTO;
use App\DTO\ProfitLossFilterDTO;
use App\Repositories\Contracts\FinanceDashboardRepositoryInterface;

class FinanceDashboardService
{
    public function __construct(

        private FinanceDashboardRepositoryInterface $repository,

        private ProfitLossService $profitLossService,

        private ARAgingService $arAgingService,

        private APAgingService $apAgingService,

    ) {}

    public function getDashboard(
        FinanceDashboardFilterDTO $dto
    ) {
        /*
        |--------------------------------------------------------------------------
        | CASH / BANK
        |--------------------------------------------------------------------------
        */

        $cashAccounts =
            $this->repository
                ->getCashPosition($dto);

        $cashPosition =
            $cashAccounts->sum(
                'balance'
            );

        /*
        |--------------------------------------------------------------------------
        | INVENTORY
        |--------------------------------------------------------------------------
        */

        $inventory =
            $this->repository
                ->getInventoryValue();

        /*
        |--------------------------------------------------------------------------
        | PROFIT & LOSS
        |--------------------------------------------------------------------------
        */

        $profitLoss =
            $this->profitLossService
                ->getReport(
                    new ProfitLossFilterDTO(

                        dateFrom :
                            $dto->dateFrom,

                        dateTo :
                            $dto->dateTo
                    )
                );

        /*
        |--------------------------------------------------------------------------
        | AR AGING
        |--------------------------------------------------------------------------
        */

        $arAging =
            $this->arAgingService
                ->getReport(
                    new ARAgingFilterDTO(

                        asOfDate :
                            $dto->dateTo
                    )
                );

        /*
        |--------------------------------------------------------------------------
        | AP AGING
        |--------------------------------------------------------------------------
        */

        $apAging =
            $this->apAgingService
                ->getReport(
                    new APAgingFilterDTO(

                        asOfDate :
                            $dto->dateTo
                    )
                );

        /*
        |--------------------------------------------------------------------------
        | LOW STOCK
        |--------------------------------------------------------------------------
        */

        $lowStock =
            $this->repository
                ->getLowStockItems();

        /*
        |--------------------------------------------------------------------------
        | NEGATIVE BANK
        |--------------------------------------------------------------------------
        */

        $negativeBank =
            $this->repository
                ->getBankNegativeAccounts(
                    $dto
                );

        /*
        |--------------------------------------------------------------------------
        | RETURN DASHBOARD
        |--------------------------------------------------------------------------
        */

        return [

            'date_from' =>
                $dto->dateFrom,

            'date_to' =>
                $dto->dateTo,

            /*
            |--------------------------------------------------------------------------
            | KPI
            |--------------------------------------------------------------------------
            */

            'cash_position' =>
                $cashPosition,

            'inventory_value' =>
                $inventory['total_value'],

            'outstanding_ar' =>
                $arAging[
                    'total_outstanding'
                ],

            'overdue_ar' =>
                $arAging[
                    'total_1_30'
                ]
                +
                $arAging[
                    'total_31_60'
                ]
                +
                $arAging[
                    'total_61_90'
                ]
                +
                $arAging[
                    'total_over_90'
                ],

            'outstanding_ap' =>
                $apAging[
                    'total_outstanding'
                ],

            'overdue_ap' =>
                $apAging[
                    'total_1_30'
                ]
                +
                $apAging[
                    'total_31_60'
                ]
                +
                $apAging[
                    'total_61_90'
                ]
                +
                $apAging[
                    'total_over_90'
                ],

            'sales' =>
                $profitLoss['revenue'],

            'cogs' =>
                $profitLoss['cogs'],

            'gross_profit' =>
                $profitLoss['gross_profit'],

            'operating_expense' =>
                $profitLoss['expense'],

            'net_profit' =>
                $profitLoss['net_profit'],

            /*
            |--------------------------------------------------------------------------
            | DETAIL
            |--------------------------------------------------------------------------
            */

            'cash_accounts' =>
                $cashAccounts,

            'inventory_items' =>
                $inventory['items'],

            'ar_aging' =>
                $arAging,

            'ap_aging' =>
                $apAging,

            'low_stock_items' =>
                $lowStock,

            'negative_bank_accounts' =>
                $negativeBank,
        ];
    }
}
<?php

namespace App\Services;

use App\DTO\APAgingFilterDTO;
use App\Models\AccountPayable;
use App\Repositories\Contracts\APAgingRepositoryInterface;
use Carbon\Carbon;

class APAgingService
{
    public function __construct(

        private APAgingRepositoryInterface $repository

    ) {}

    public function getReport(
        APAgingFilterDTO $dto
    ) {
        $payables =
            $this->repository
                ->getAging($dto);

        $current = [];
        $days1To30 = [];
        $days31To60 = [];
        $days61To90 = [];
        $over90 = [];

        $totalCurrent = 0;
        $total1To30 = 0;
        $total31To60 = 0;
        $total61To90 = 0;
        $totalOver90 = 0;

        $asOfDate =
            Carbon::parse(
                $dto->asOfDate
            );

        foreach ($payables as $payable) {

            $dueDate =
                Carbon::parse(
                    $payable->due_date
                );

            $daysOverdue =
                $dueDate->lt($asOfDate)
                    ? $dueDate->diffInDays(
                        $asOfDate
                    )
                    : 0;

            $row =
                $this->formatRow(
                    $payable,
                    $daysOverdue
                );

            /*
            |--------------------------------------------------------------------------
            | CURRENT
            |--------------------------------------------------------------------------
            */

            if ($daysOverdue <= 0) {

                $current[] = $row;

                $totalCurrent +=
                    (float) $payable->balance_amount;

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | 1 - 30 DAYS
            |--------------------------------------------------------------------------
            */

            if ($daysOverdue <= 30) {

                $days1To30[] = $row;

                $total1To30 +=
                    (float) $payable->balance_amount;

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | 31 - 60 DAYS
            |--------------------------------------------------------------------------
            */

            if ($daysOverdue <= 60) {

                $days31To60[] = $row;

                $total31To60 +=
                    (float) $payable->balance_amount;

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | 61 - 90 DAYS
            |--------------------------------------------------------------------------
            */

            if ($daysOverdue <= 90) {

                $days61To90[] = $row;

                $total61To90 +=
                    (float) $payable->balance_amount;

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | > 90 DAYS
            |--------------------------------------------------------------------------
            */

            $over90[] = $row;

            $totalOver90 +=
                (float) $payable->balance_amount;
        }

        return [

            'as_of_date' =>
                $dto->asOfDate,

            'current' =>
                $current,

            'total_current' =>
                $totalCurrent,

            'days_1_30' =>
                $days1To30,

            'total_1_30' =>
                $total1To30,

            'days_31_60' =>
                $days31To60,

            'total_31_60' =>
                $total31To60,

            'days_61_90' =>
                $days61To90,

            'total_61_90' =>
                $total61To90,

            'over_90' =>
                $over90,

            'total_over_90' =>
                $totalOver90,

            'total_outstanding' =>
                $totalCurrent
                +
                $total1To30
                +
                $total31To60
                +
                $total61To90
                +
                $totalOver90,
        ];
    }

    protected function formatRow(
        AccountPayable $payable,
        int $daysOverdue
    ): array {

        return [

            'payable_id' =>
                $payable->id,

            'reference_type' =>
                $payable->reference_type,

            'reference_id' =>
                $payable->reference_id,

            'supplier_name' =>
                $payable->supplier_name,

            'invoice_date' =>
                $payable->invoice_date,

            'due_date' =>
                $payable->due_date,

            'amount' =>
                (float) $payable->amount,

            'paid_amount' =>
                (float) $payable->paid_amount,

            'balance_amount' =>
                (float) $payable->balance_amount,

            'days_overdue' =>
                $daysOverdue,
        ];
    }
}
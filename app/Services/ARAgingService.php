<?php

namespace App\Services;

use App\DTO\ARAgingFilterDTO;
use App\Repositories\Contracts\ARAgingRepositoryInterface;

class ARAgingService
{
    public function __construct(

        private ARAgingRepositoryInterface $repository

    ) {}

    public function getReport(
        ARAgingFilterDTO $dto
    ) {
        $receivables =
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

        foreach (
            $receivables
            as $receivable
        ) {

            $dueDate =
                $receivable->due_date;

            $asOfDate =
                \Carbon\Carbon::parse(
                    $dto->asOfDate
                );

            $daysOverdue =
                $dueDate->lt($asOfDate)
                    ? $dueDate->diffInDays(
                        $asOfDate
                    )
                    : 0;

            /*
            |--------------------------------------------------------------------------
            | CURRENT
            |--------------------------------------------------------------------------
            */

            if (
                $daysOverdue <= 0
            ) {

                $current[] =
                    $this->formatRow(
                        $receivable,
                        0
                    );

                $totalCurrent +=
                    (float)
                    $receivable
                        ->balance_amount;

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | 1 - 30 DAYS
            |--------------------------------------------------------------------------
            */

            if (
                $daysOverdue <= 30
            ) {

                $days1To30[] =
                    $this->formatRow(
                        $receivable,
                        $daysOverdue
                    );

                $total1To30 +=
                    (float)
                    $receivable
                        ->balance_amount;

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | 31 - 60 DAYS
            |--------------------------------------------------------------------------
            */

            if (
                $daysOverdue <= 60
            ) {

                $days31To60[] =
                    $this->formatRow(
                        $receivable,
                        $daysOverdue
                    );

                $total31To60 +=
                    (float)
                    $receivable
                        ->balance_amount;

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | 61 - 90 DAYS
            |--------------------------------------------------------------------------
            */

            if (
                $daysOverdue <= 90
            ) {

                $days61To90[] =
                    $this->formatRow(
                        $receivable,
                        $daysOverdue
                    );

                $total61To90 +=
                    (float)
                    $receivable
                        ->balance_amount;

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | > 90 DAYS
            |--------------------------------------------------------------------------
            */

            $over90[] =
                $this->formatRow(
                    $receivable,
                    $daysOverdue
                );

            $totalOver90 +=
                (float)
                $receivable
                    ->balance_amount;
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
        $receivable,
        int $daysOverdue
    ): array {

        return [

            'receivable_id' =>
                $receivable->id,

            'customer_id' =>
                $receivable->customer_id,

            'customer_name' =>
                $receivable->customer?->name,

            'sales_invoice_id' =>
                $receivable
                    ->sales_invoice_id,

            'invoice_no' =>
                $receivable
                    ->salesInvoice?->invoice_no,

            'invoice_date' =>
                $receivable
                    ->invoice_date,

            'due_date' =>
                $receivable
                    ->due_date,

            'amount' =>
                (float)
                $receivable->amount,

            'paid_amount' =>
                (float)
                $receivable->paid_amount,

            'balance_amount' =>
                (float)
                $receivable->balance_amount,

            'days_overdue' =>
                $daysOverdue,
        ];
    }
}
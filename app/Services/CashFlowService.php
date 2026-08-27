<?php

namespace App\Services;

use App\DTO\CashFlowFilterDTO;
use App\Repositories\Contracts\CashFlowRepositoryInterface;

class CashFlowService
{
    public function __construct(

        private CashFlowRepositoryInterface $repository

    ) {}

    public function getReport(
        CashFlowFilterDTO $dto
    ) {
        $transactions =
            $this->repository
                ->getCashTransactions($dto);

        $operating = [];

        $investing = [];

        $financing = [];

        $other = [];

        $totalOperating = 0;

        $totalInvesting = 0;

        $totalFinancing = 0;

        foreach ($transactions as $transaction) {

            $debit =
                (float) $transaction->debit;

            $credit =
                (float) $transaction->credit;

            /*
            |--------------------------------------------------------------------------
            | Cash In / Cash Out
            |--------------------------------------------------------------------------
            */

            $cashIn =
                $debit > 0
                    ? $debit
                    : 0;

            $cashOut =
                $credit > 0
                    ? $credit
                    : 0;

            $net =
                $cashIn
                -
                $cashOut;

            $referenceType =
                $transaction
                    ->journal
                    ->reference_type;

            /*
            |--------------------------------------------------------------------------
            | CLASSIFICATION
            |--------------------------------------------------------------------------
            */

            switch ($referenceType) {

                /*
                |--------------------------------------------------------------------------
                | OPERATING
                |--------------------------------------------------------------------------
                */

                case 'CUSTOMER_RECEIPT':

                    $category =
                        'OPERATING';

                    $operating[] = [

                        'date' =>
                            $transaction
                                ->journal
                                ->journal_date,

                        'reference_type' =>
                            $referenceType,

                        'reference_id' =>
                            $transaction
                                ->journal
                                ->reference_id,

                        'account_code' =>
                            $transaction
                                ->account
                                ->code,

                        'account_name' =>
                            $transaction
                                ->account
                                ->name,

                        'cash_in' =>
                            $cashIn,

                        'cash_out' =>
                            $cashOut,

                        'net' =>
                            $net,
                    ];

                    $totalOperating +=
                        $net;

                    break;

                /*
                |--------------------------------------------------------------------------
                | PURCHASE PAYMENT
                |--------------------------------------------------------------------------
                */

                case 'PAYMENT_VOUCHER':

                    $category =
                        'OPERATING';

                    $operating[] = [

                        'date' =>
                            $transaction
                                ->journal
                                ->journal_date,

                        'reference_type' =>
                            $referenceType,

                        'reference_id' =>
                            $transaction
                                ->journal
                                ->reference_id,

                        'account_code' =>
                            $transaction
                                ->account
                                ->code,

                        'account_name' =>
                            $transaction
                                ->account
                                ->name,

                        'cash_in' =>
                            $cashIn,

                        'cash_out' =>
                            $cashOut,

                        'net' =>
                            $net,
                    ];

                    $totalOperating +=
                        $net;

                    break;

                /*
                |--------------------------------------------------------------------------
                | OPENING / CAPITAL
                |--------------------------------------------------------------------------
                */

                case 'OPENING':

                    $category =
                        'FINANCING';

                    $financing[] = [

                        'date' =>
                            $transaction
                                ->journal
                                ->journal_date,

                        'reference_type' =>
                            $referenceType,

                        'reference_id' =>
                            $transaction
                                ->journal
                                ->reference_id,

                        'account_code' =>
                            $transaction
                                ->account
                                ->code,

                        'account_name' =>
                            $transaction
                                ->account
                                ->name,

                        'cash_in' =>
                            $cashIn,

                        'cash_out' =>
                            $cashOut,

                        'net' =>
                            $net,
                    ];

                    $totalFinancing +=
                        $net;

                    break;

                default:

                    $other[] = [

                        'date' =>
                            $transaction
                                ->journal
                                ->journal_date,

                        'reference_type' =>
                            $referenceType,

                        'reference_id' =>
                            $transaction
                                ->journal
                                ->reference_id,

                        'account_code' =>
                            $transaction
                                ->account
                                ->code,

                        'account_name' =>
                            $transaction
                                ->account
                                ->name,

                        'cash_in' =>
                            $cashIn,

                        'cash_out' =>
                            $cashOut,

                        'net' =>
                            $net,
                    ];

                    break;
            }
        }

        $netCashFlow =
            $totalOperating
            +
            $totalInvesting
            +
            $totalFinancing;

        return [

            'date_from' =>
                $dto->dateFrom,

            'date_to' =>
                $dto->dateTo,

            'operating' =>
                $operating,

            'total_operating' =>
                $totalOperating,

            'investing' =>
                $investing,

            'total_investing' =>
                $totalInvesting,

            'financing' =>
                $financing,

            'total_financing' =>
                $totalFinancing,

            'other' =>
                $other,

            'net_cash_flow' =>
                $netCashFlow,

        ];
    }
}
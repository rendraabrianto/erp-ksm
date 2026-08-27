<?php

namespace App\Services;

use App\DTO\CustomerReceiptDTO;
use App\Models\AccountReceivable;
use App\Repositories\Contracts\CustomerReceiptRepositoryInterface;
use Illuminate\Support\Facades\DB;

class CustomerReceiptService
{
    public function __construct(

        private CustomerReceiptRepositoryInterface $repository,

        private DocumentSequenceService $documentSequenceService,

        private AutoJournalService $autoJournalService,

        private AuditLogService $auditService,
    ) {}

    public function create(
        CustomerReceiptDTO $dto
    )
    {
        return DB::transaction(function () use ($dto) {

            $ar =
                AccountReceivable::findOrFail(
                    $dto->accountReceivableId
                );

            if (
                $dto->amount >
                $ar->balance_amount
            ) {
                throw new \Exception(
                    'Payment exceeds receivable balance'
                );
            }

            $receipt =
                $this->repository->create([

                    'receipt_no' =>
                        $this->documentSequenceService
                            ->next('CR'),

                    'customer_id' =>
                        $dto->customerId,

                    'account_receivable_id' =>
                        $dto->accountReceivableId,

                    'cash_bank_account_id' =>
                        $dto->cashBankAccountId,

                    'receipt_date' =>
                        now()->toDateString(),

                    'amount' =>
                        $dto->amount,

                    'remarks' =>
                        $dto->remarks,

                    'created_by' =>
                        $dto->createdBy,
                ]);

            /*
            |--------------------------------------------------------------------------
            | Update AR
            |--------------------------------------------------------------------------
            */

            $ar->paid_amount += $dto->amount;

            $ar->balance_amount =
                $ar->amount -
                $ar->paid_amount;

            if ($ar->balance_amount <= 0) {

                $ar->status = 'PAID';

            } elseif ($ar->paid_amount > 0) {

                $ar->status = 'PARTIAL';
            }

            $ar->save();

            /*
            |--------------------------------------------------------------------------
            | Auto Journal
            |--------------------------------------------------------------------------
            */

            $this->autoJournalService->customerReceipt(
                    cashBankAccountId : $dto->cashBankAccountId,
                    amount : $dto->amount,
                    referenceId : $receipt->id,
                    userId : $dto->createdBy
                );

            /*
            |--------------------------------------------------------------------------
            | Audit
            |--------------------------------------------------------------------------
            */

            $this->auditService->log(

                module : 'Customer Receipt',

                action : 'CREATE',

                referenceType : 'CustomerReceipt',

                referenceId : $receipt->id,

                oldValues : null,

                newValues : [

                    'receipt_no' =>
                        $receipt->receipt_no
                ]
            );

            return $receipt;
        });
    }
}
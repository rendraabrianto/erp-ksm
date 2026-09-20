<?php

namespace App\Services;

use App\DTO\CustomerReceiptDTO;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Repositories\Contracts\CustomerReceiptRepositoryInterface;
use Illuminate\Support\Facades\DB;

class CustomerReceiptService
{
    public function __construct(
        private CustomerReceiptRepositoryInterface $repository,
        private DocumentSequenceService $documentSequenceService,
        private AutoJournalService $autoJournalService,
        private AuditLogService $auditService,
        protected CompanyGuardService $companyGuardService,
        private AccountingAccountResolverService $accountResolver,
    ) {}

    public function create(
        CustomerReceiptDTO $dto
    )
    {
        return DB::transaction(function () use ($dto) {

            /*
            |--------------------------------------------------------------------------
            | Lock Account Receivable
            |--------------------------------------------------------------------------
            |
            | Account Receivable is the authoritative ownership source.
            |
            | createdBy represents the actor performing the transaction,
            | not the company owner of the transaction.
            |
            | lockForUpdate also prevents two receipt transactions from
            | reading and updating the same receivable balance concurrently.
            |
            */

            $ar =
                AccountReceivable::query()
                    ->whereKey(
                        $dto->accountReceivableId
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

            /*
            |--------------------------------------------------------------------------
            | Resolve Company From Account Receivable
            |--------------------------------------------------------------------------
            */

            $companyId =
                (int) $ar->company_id;

            /*
            |--------------------------------------------------------------------------
            | Validate Actor Company
            |--------------------------------------------------------------------------
            |
            | The Account Receivable owns the transaction company.
            | createdBy is only the actor and must belong to the same company.
            |
            */

            $this->companyGuardService
                ->assertActorBelongsToCompany(
                    $dto->createdBy,
                    $companyId
                );

            /*
            |--------------------------------------------------------------------------
            | Cash / Bank Account Company Guard
            |--------------------------------------------------------------------------
            |
            | Selected receipt account must belong to the same company as the AR.
            | Validate before sequence generation, receipt creation, AR mutation,
            | and journal posting.
            |
            */

            $this->accountResolver
                ->accountForCompany(
                    $dto->cashBankAccountId,
                    $companyId,
                    'Cash/bank account'
                );

            /*
            |--------------------------------------------------------------------------
            | Validate Amount
            |--------------------------------------------------------------------------
            */

            if ($dto->amount <= 0) {

                throw new \RuntimeException(
                    'Receipt amount must be greater than zero.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Validate Customer Ownership
            |--------------------------------------------------------------------------
            */

            if (
                (int) $ar->customer_id !==
                (int) $dto->customerId
            ) {

                throw new \RuntimeException(
                    'Customer does not match account receivable.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Customer Company Guard
            |--------------------------------------------------------------------------
            |
            | Customer yang dimiliki Account Receivable wajib berasal dari company
            | yang sama dengan Account Receivable / Customer Receipt.
            |
            */

            $customer =
                Customer::query()
                    ->whereKey(
                        $ar->customer_id
                    )
                    ->firstOrFail();

            if (
                (int) $customer->company_id
                !==
                $companyId
            ) {
                throw new \RuntimeException(
                    'Customer does not belong to transaction company.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Validate Receivable Status
            |--------------------------------------------------------------------------
            */

            if (
                $ar->status === 'PAID' ||
                (float) $ar->balance_amount <= 0
            ) {

                throw new \RuntimeException(
                    'Account receivable is already paid.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Validate Receivable Balance
            |--------------------------------------------------------------------------
            */

            if (
                $dto->amount >
                (float) $ar->balance_amount
            ) {

                throw new \RuntimeException(
                    'Payment exceeds receivable balance'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Create Customer Receipt
            |--------------------------------------------------------------------------
            */

            $receipt =
                $this->repository->create([

                    'company_id' =>
                        $companyId,

                    'receipt_no' =>
                        $this->documentSequenceService
                            ->next('CR'),

                    'customer_id' =>
                        $customer->id,

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
            | Update Account Receivable
            |--------------------------------------------------------------------------
            */

            $ar->paid_amount +=
                $dto->amount;

            $ar->balance_amount =
                $ar->amount -
                $ar->paid_amount;

            if (
                $ar->balance_amount <= 0
            ) {

                $ar->status =
                    'PAID';

            } elseif (
                $ar->paid_amount > 0
            ) {

                $ar->status =
                    'PARTIAL';
            }

            $ar->save();

            /*
            |--------------------------------------------------------------------------
            | Auto Journal
            |--------------------------------------------------------------------------
            |
            | Accounting mapping must follow the Account Receivable company,
            | not the transaction actor.
            |
            */

            $this->autoJournalService
                ->customerReceipt(
                    cashBankAccountId:
                        $dto->cashBankAccountId,

                    amount:
                        $dto->amount,

                    referenceId:
                        $receipt->id,

                    userId:
                        $dto->createdBy,

                    companyId:
                        $companyId,
                );

            /*
            |--------------------------------------------------------------------------
            | Audit
            |--------------------------------------------------------------------------
            */

            $this->auditService->log(

                module:
                    'Customer Receipt',

                action:
                    'CREATE',

                referenceType:
                    'CustomerReceipt',

                referenceId:
                    $receipt->id,

                oldValues:
                    null,

                newValues: [

                    'receipt_no' =>
                        $receipt->receipt_no,

                    'company_id' =>
                        $companyId,

                    'account_receivable_id' =>
                        $ar->id,
                ]
            );

            return $receipt;
        });
    }
}
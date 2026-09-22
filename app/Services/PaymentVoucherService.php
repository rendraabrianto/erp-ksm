<?php

namespace App\Services;

use App\DTO\PaymentVoucherDTO;
use App\Models\AccountPayable;
use App\Repositories\Contracts\PaymentVoucherRepositoryInterface;
use Illuminate\Support\Facades\DB;

class PaymentVoucherService
{
    public function __construct(
        private PaymentVoucherRepositoryInterface $repository,
        private DocumentSequenceService $documentSequenceService,
        private AutoJournalService $autoJournalService,
        private AuditLogService $auditService,
        private CompanyGuardService $companyGuardService,
        private AccountingAccountResolverService $accountResolver,
    ) {}

    public function create(
        PaymentVoucherDTO $dto
    )
    {
        return DB::transaction(
            function () use ($dto) {

                /*
                |--------------------------------------------------------------------------
                | Account Payable Ownership Authority
                |--------------------------------------------------------------------------
                |
                | Payment Voucher harus mewarisi company dari Account Payable.
                |
                | created_by hanya actor/audit information dan tidak boleh
                | menentukan ownership dokumen.
                |
                */

                $ap =
                    AccountPayable::query()
                        ->whereKey(
                            $dto->accountPayableId
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $companyId =
                    (int) $ap->company_id;

                /*
                |--------------------------------------------------------------------------
                | Actor Company Guard
                |--------------------------------------------------------------------------
                |
                | Account Payable is the authoritative company ownership source.
                | createdBy is only the payment actor.
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
                | Selected payment account must belong to the same company as the AP.
                | Validate before sequence generation, voucher creation, AP mutation,
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
                | Business Guards
                |--------------------------------------------------------------------------
                */

                if ($ap->status === 'PAID') {
                    throw new \Exception(
                        'Account payable already paid'
                    );
                }

                if (
                    $dto->amount >
                    $ap->balance_amount
                ) {
                    throw new \Exception(
                        'Payment exceeds AP balance'
                    );
                }

                if ($dto->amount <= 0) {
                    throw new \Exception(
                        'Payment amount must be greater than zero'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Create Payment Voucher
                |--------------------------------------------------------------------------
                */

                $voucher =
                    $this->repository->create([
                        'company_id' =>
                            $companyId,

                        'voucher_no' =>
                            $this
                                ->documentSequenceService
                                ->next(
                                    $companyId,
                                    'PV'
                                ),

                        'voucher_date' =>
                            now()->toDateString(),

                        'account_payable_id' =>
                            $ap->id,

                        'cash_bank_account_id' =>
                            $dto->cashBankAccountId,

                        'amount' =>
                            $dto->amount,

                        'payment_method' =>
                            $dto->paymentMethod,

                        'remarks' =>
                            $dto->remarks,

                        'created_by' =>
                            $dto->createdBy,
                    ]);

                /*
                |--------------------------------------------------------------------------
                | Update Account Payable
                |--------------------------------------------------------------------------
                */

                $newPaid =
                    $ap->paid_amount
                    +
                    $dto->amount;

                $newBalance =
                    $ap->amount
                    -
                    $newPaid;

                $ap->update([
                    'paid_amount' =>
                        $newPaid,

                    'balance_amount' =>
                        max(
                            0,
                            $newBalance
                        ),

                    'status' =>
                        $newBalance <= 0
                            ? 'PAID'
                            : 'PARTIAL',
                ]);

                /*
                |--------------------------------------------------------------------------
                | Journal
                |--------------------------------------------------------------------------
                |
                | Accounting mapping harus menggunakan company milik AP,
                | bukan company creator.
                |
                */

                $this
                    ->autoJournalService
                    ->paymentVoucher(
                        amount:
                            $dto->amount,

                        cashBankAccountId:
                            $dto->cashBankAccountId,

                        referenceId:
                            $voucher->id,

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

                $this
                    ->auditService
                    ->log(
                        module:
                            'Payment Voucher',

                        action:
                            'CREATE',

                        referenceType:
                            'PaymentVoucher',

                        referenceId:
                            $voucher->id,

                        oldValues:
                            null,

                        newValues: [
                            'voucher_no' =>
                                $voucher->voucher_no,

                            'company_id' =>
                                $voucher->company_id,

                            'account_payable_id' =>
                                $ap->id,
                        ],
                    );

                return $voucher;
            }
        );
    }
}
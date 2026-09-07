<?php

namespace App\Services;

use App\DTO\PaymentVoucherDTO;
use App\Repositories\Contracts\PaymentVoucherRepositoryInterface;
use App\Models\AccountPayable;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class PaymentVoucherService
{
    public function __construct(
        private PaymentVoucherRepositoryInterface $repository,
        private DocumentSequenceService $documentSequenceService,
        private AutoJournalService $autoJournalService,
        private AuditLogService $auditService,
    ) {}

    public function create(
        PaymentVoucherDTO $dto
    )
    {
        return DB::transaction(
            function () use ($dto) {
                $user = User::query()
                        ->whereKey(
                            $dto->createdBy
                        )->firstOrFail();

                $companyId = (int) $user->company_id;
                $ap = AccountPayable::lockForUpdate()
                ->findOrFail(
                    $dto->accountPayableId
                );

                if ($ap->status === 'PAID') {
                    throw new \Exception(
                        'Account payable already paid'
                    );
                }

                if (
                    $dto->amount > $ap->balance_amount
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

                $voucher =
                    $this->repository->create([

                        'voucher_no' =>
                            $this
                                ->documentSequenceService
                                ->next('PV'),

                        // 'voucher_date' => now(),
                        'voucher_date' => now()->toDateString(),
                        
                        'account_payable_id' => $dto->accountPayableId,

                        'cash_bank_account_id' => $dto->cashBankAccountId,

                        'amount' => $dto->amount,

                        'payment_method' => $dto->paymentMethod,

                        'remarks' => $dto->remarks,

                        'created_by' => $dto->createdBy,
                    ]);

                /*
                 * Update AP
                 */

                $newPaid = $ap->paid_amount + $dto->amount;

                $newBalance = $ap->amount - $newPaid;

                $ap->update([

                    'paid_amount' =>
                        $newPaid,

                    'balance_amount' =>
                        max(0, $newBalance),

                    'status' =>
                        $newBalance <= 0
                            ? 'PAID'
                            : 'PARTIAL',
                ]);

                /*
                 * Journal
                 */

                $this->autoJournalService
                    ->paymentVoucher(
                        amount: $dto->amount,
                        cashBankAccountId: $dto->cashBankAccountId,
                        referenceId: $voucher->id,
                        userId: $dto->createdBy,
                        companyId: $companyId
                    );
                /*
                 * Audit
                 */

                $this->auditService->log(
                    module : 'Payment Voucher',
                    action : 'CREATE',
                    referenceType : 'PaymentVoucher',
                    referenceId : $voucher->id,
                    oldValues : null,
                    newValues : [
                        'voucher_no' =>
                            $voucher->voucher_no,
                    ]
                );

                return $voucher;
            }
        );
    }
}
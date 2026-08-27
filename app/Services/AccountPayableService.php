<?php

namespace App\Services;

use App\DTO\AccountPayableDTO;
use App\Repositories\Contracts\AccountPayableRepositoryInterface;

class AccountPayableService
{
    public function __construct(
        private AccountPayableRepositoryInterface $repository
    ) {}

    public function create(
        AccountPayableDTO $dto
    )
    {
        return $this->repository->create([
            'reference_type' => $dto->referenceType,
            'reference_id'   => $dto->referenceId,
            'supplier_name'  => $dto->supplierName,
            'invoice_date'   => $dto->invoiceDate,
            'due_date'       => $dto->dueDate,
            'amount'         => $dto->amount,
            'paid_amount'    => 0,
            'balance_amount' => $dto->amount,
            'status'         => 'OPEN',
        ]);
    }

    public function applyPayment(
        int $accountPayableId,
        float $amount
    ): void {

        $ap = $this->repository->find(
            $accountPayableId
        );

        $paidAmount =
            $ap->paid_amount + $amount;

        $balanceAmount =
            $ap->amount - $paidAmount;

        $ap->update([

            'paid_amount'
                => $paidAmount,

            'balance_amount'
                => $balanceAmount,

            'status'
                => $balanceAmount <= 0
                    ? 'PAID'
                    : 'PARTIAL',
        ]);
    }
}
<?php

namespace App\Repositories\Eloquent;

use App\DTO\ARAgingFilterDTO;
use App\Models\AccountReceivable;
use App\Repositories\Contracts\ARAgingRepositoryInterface;

class ARAgingRepository implements
    ARAgingRepositoryInterface
{
    public function getAging(
        int $companyId,
        ARAgingFilterDTO $dto
    ) {
        return AccountReceivable::query()

            ->with([
                'customer',
                'salesInvoice',
            ])

            ->where(
                'company_id',
                $companyId
            )

            ->where(
                'invoice_date',
                '<=',
                $dto->asOfDate
            )

            ->where(
                'balance_amount',
                '>',
                0
            )

            ->when(
                $dto->customerId,
                function ($q) use (
                    $dto,
                    $companyId
                ) {
                    $q->where(
                        'customer_id',
                        $dto->customerId
                    );

                    $q->whereHas(
                        'customer',
                        function ($customer) use (
                            $companyId
                        ) {
                            $customer->where(
                                'company_id',
                                $companyId
                            );
                        }
                    );
                }
            )

            ->orderBy(
                'due_date'
            )

            ->get();
    }
}
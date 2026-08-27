<?php

namespace App\Repositories\Eloquent;

use App\DTO\ARAgingFilterDTO;
use App\Models\AccountReceivable;
use App\Repositories\Contracts\ARAgingRepositoryInterface;

class ARAgingRepository
implements ARAgingRepositoryInterface
{
    public function getAging(
        ARAgingFilterDTO $dto
    ) {
        return AccountReceivable::query()

            ->with([
                'customer',
                'salesInvoice',
            ])

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
                function ($q) use ($dto) {

                    $q->where(
                        'customer_id',
                        $dto->customerId
                    );
                }
            )

            ->orderBy(
                'due_date'
            )

            ->get();
    }
}
<?php

namespace App\Repositories\Eloquent;

use App\DTO\APAgingFilterDTO;
use App\Models\AccountPayable;
use App\Repositories\Contracts\APAgingRepositoryInterface;

class APAgingRepository implements
    APAgingRepositoryInterface
{
    public function getAging(
        int $companyId,
        APAgingFilterDTO $dto
    ) {
        return AccountPayable::query()

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
                $dto->supplierName,
                function ($q) use ($dto) {

                    $q->where(
                        'supplier_name',
                        $dto->supplierName
                    );
                }
            )

            ->orderBy(
                'due_date'
            )

            ->get();
    }
}
<?php

namespace App\Repositories\Eloquent;

use App\Models\PaymentVoucher;

use App\Repositories\Contracts\PaymentVoucherRepositoryInterface;

class PaymentVoucherRepository
implements PaymentVoucherRepositoryInterface
{
    public function create(
        array $data
    )
    {
        return PaymentVoucher::create(
            $data
        );
    }

    public function find(
        int $id
    )
    {
        return PaymentVoucher::findOrFail(
            $id
        );
    }

    public function paginate(
        int $perPage = 15
    )
    {
        return PaymentVoucher::latest()
            ->paginate($perPage);
    }
}
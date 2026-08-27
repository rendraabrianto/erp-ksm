<?php

namespace App\Repositories\Eloquent;

use App\Models\CustomerReceipt;

use App\Repositories\Contracts\CustomerReceiptRepositoryInterface;

class CustomerReceiptRepository
implements CustomerReceiptRepositoryInterface
{
    public function create(
        array $data
    )
    {
        return CustomerReceipt::create(
            $data
        );
    }
}
<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\SalesInvoiceRepositoryInterface;
use App\Models\SalesInvoice;

class SalesInvoiceRepository
implements SalesInvoiceRepositoryInterface
{
    public function create(
        array $data
    )
    {
        return SalesInvoice::create(
            $data
        );
    }
}
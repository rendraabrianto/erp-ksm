<?php

namespace App\Repositories\Eloquent;

use App\Models\PurchaseInvoice;
use App\Repositories\Contracts\PurchaseInvoiceRepositoryInterface;

class PurchaseInvoiceRepository
implements PurchaseInvoiceRepositoryInterface
{
    public function create(
        array $data
    ) {
        return PurchaseInvoice::create(
            $data
        );
    }

    public function find(
        int $id
    ) {
        return PurchaseInvoice::findOrFail(
            $id
        );
    }

    public function paginate(
        int $perPage = 15
    ) {
        return PurchaseInvoice::paginate(
            $perPage
        );
    }
}
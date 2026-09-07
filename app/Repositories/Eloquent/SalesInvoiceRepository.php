<?php

namespace App\Repositories\Eloquent;

use App\Models\SalesInvoice;
use App\Repositories\Contracts\SalesInvoiceRepositoryInterface;

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

    public function findByDeliveryOrder(
        int $deliveryOrderId
    )
    {
        return SalesInvoice::query()
            ->where(
                'delivery_order_id',
                $deliveryOrderId
            )
            ->first();
    }
}
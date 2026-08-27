<?php

namespace App\Repositories\Contracts;

interface SalesInvoiceRepositoryInterface
{
    public function create(
        array $data
    );
    public function findByDeliveryOrder(
        int $deliveryOrderId
    );
}
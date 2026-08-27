<?php

namespace App\Repositories\Contracts;

interface PurchaseInvoiceRepositoryInterface
{
    public function create(
        array $data
    );

    public function find(
        int $id
    );

    public function paginate(
        int $perPage = 15
    );
}
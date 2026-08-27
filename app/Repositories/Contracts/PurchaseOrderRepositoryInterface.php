<?php

namespace App\Repositories\Contracts;

use App\Models\PurchaseOrder;

interface PurchaseOrderRepositoryInterface
{
    public function create(
        array $data
    ): PurchaseOrder;

    public function find(
        int $id
    ): ?PurchaseOrder;
}
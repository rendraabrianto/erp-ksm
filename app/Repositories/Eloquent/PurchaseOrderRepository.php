<?php

namespace App\Repositories\Eloquent;

use App\Models\PurchaseOrder;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;

class PurchaseOrderRepository
implements PurchaseOrderRepositoryInterface
{
    public function create(
        array $data
    ): PurchaseOrder
    {
        return PurchaseOrder::create($data);
    }

    public function find(
        int $id
    ): ?PurchaseOrder
    {
        return PurchaseOrder::with([
            'details.item',
            'purchaseRequest',
            'creator',
        ])->find($id);
    }
}
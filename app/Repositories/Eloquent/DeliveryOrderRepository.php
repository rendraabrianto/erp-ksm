<?php

namespace App\Repositories\Eloquent;

use App\Models\DeliveryOrder;
use App\Repositories\Contracts\DeliveryOrderRepositoryInterface;

class DeliveryOrderRepository
implements DeliveryOrderRepositoryInterface
{
    public function create(
        array $data
    )
    {
        return DeliveryOrder::create(
            $data
        );
    }
}
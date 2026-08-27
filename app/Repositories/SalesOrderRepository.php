<?php

namespace App\Repositories;

use App\Models\SalesOrder;

use App\Repositories\Contracts\SalesOrderRepositoryInterface;

class SalesOrderRepository
implements SalesOrderRepositoryInterface
{
    public function create(
        array $data
    )
    {
        return SalesOrder::create(
            $data
        );
    }

    public function find(
        int $id
    )
    {
        return SalesOrder::findOrFail(
            $id
        );
    }
}
<?php

namespace App\Repositories\Contracts;

interface DeliveryOrderRepositoryInterface
{
    public function create(
        array $data
    );
}
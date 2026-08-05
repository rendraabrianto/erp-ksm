<?php

namespace App\Services;

use App\Models\Warehouse;
use App\Repositories\Contracts\WarehouseRepositoryInterface;

class WarehouseService
{
    public function __construct(
        private WarehouseRepositoryInterface $repository
    ) {}

    public function paginate()
    {
        return $this->repository->paginate();
    }

    public function create(
        array $data
    ): Warehouse
    {
        return $this->repository->create([
            'company_id' => $data['company_id'],
            'branch_id'  => $data['branch_id'],
            'code'       => $data['code'],
            'name'       => $data['name'],
            'phone'      => $data['phone'] ?? null,
            'email'      => $data['email'] ?? null,
            'address'    => $data['address'] ?? null,
            'is_active'  => isset($data['is_active']),
        ]);
    }
}
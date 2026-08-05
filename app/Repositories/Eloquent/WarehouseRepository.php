<?php

namespace App\Repositories\Eloquent;

use App\Models\Warehouse;
use App\Repositories\Contracts\WarehouseRepositoryInterface;

class WarehouseRepository
implements WarehouseRepositoryInterface
{
    public function paginate(
        int $perPage = 10
    )
    {
        return Warehouse::with([
            'company',
            'branch'
        ])
        ->latest()
        ->paginate($perPage);
    }

    public function find(
        int $id
    ): ?Warehouse
    {
        return Warehouse::find($id);
    }

    public function create(
        array $data
    ): Warehouse
    {
        return Warehouse::create($data);
    }

    public function update(
        Warehouse $warehouse,
        array $data
    ): bool
    {
        return $warehouse->update($data);
    }

    public function delete(
        Warehouse $warehouse
    ): bool
    {
        return $warehouse->delete();
    }
}
<?php

namespace App\Repositories\Contracts;

use App\Models\Warehouse;

interface WarehouseRepositoryInterface
{
    public function paginate(int $perPage = 10);

    public function find(int $id): ?Warehouse;

    public function create(array $data): Warehouse;

    public function update(
        Warehouse $warehouse,
        array $data
    ): bool;

    public function delete(
        Warehouse $warehouse
    ): bool;
}
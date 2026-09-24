<?php

namespace App\Repositories\Contracts;
use App\Models\Warehouse;

interface WarehouseRepositoryInterface
{
    public function paginate(
        int $companyId,
        int $perPage = 10
    );

    public function find(
        int $id,
        int $companyId
    ): ?Warehouse;

    public function create(
        array $data
    ): Warehouse;

    public function update(
        int $id,
        int $companyId,
        array $data
    ): bool;

    public function delete(
        int $id,
        int $companyId
    ): bool;
}
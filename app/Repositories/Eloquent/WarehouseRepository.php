<?php

namespace App\Repositories\Eloquent;

use App\Models\Warehouse;
use App\Repositories\Contracts\WarehouseRepositoryInterface;

class WarehouseRepository implements WarehouseRepositoryInterface
{
    public function paginate(
        int $companyId,
        int $perPage = 10
    ) {
        return Warehouse::query()
            ->with([
                'company',
                'branch',
            ])
            ->where('company_id', $companyId)
            ->latest()
            ->paginate($perPage);
    }

    public function find(
        int $id,
        int $companyId
    ): ?Warehouse {
        return Warehouse::query()
            ->where('company_id', $companyId)
            ->whereKey($id)
            ->first();
    }

    public function create(
        array $data
    ): Warehouse {
        return Warehouse::create($data);
    }

    public function update(
        int $id,
        int $companyId,
        array $data
    ): bool {
        $warehouse = $this->find(
            $id,
            $companyId
        );

        if (!$warehouse) {
            return false;
        }

        return $warehouse->update($data);
    }

    public function delete(
        int $id,
        int $companyId
    ): bool {
        $warehouse = $this->find(
            $id,
            $companyId
        );

        if (!$warehouse) {
            return false;
        }

        return $warehouse->delete();
    }
}
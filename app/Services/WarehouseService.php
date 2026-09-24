<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Warehouse;
use App\Repositories\Contracts\WarehouseRepositoryInterface;
use RuntimeException;

class WarehouseService
{
    public function __construct(
        private WarehouseRepositoryInterface $repository
    ) {}

    public function paginate(
        int $companyId,
        int $perPage = 10
    ) {
        return $this->repository->paginate(
            $companyId,
            $perPage
        );
    }

    public function find(
        int $id,
        int $companyId
    ): ?Warehouse {
        return $this->repository->find(
            $id,
            $companyId
        );
    }

    public function create(
        int $companyId,
        array $data
    ): Warehouse {
        $branch = Branch::query()
            ->whereKey($data['branch_id'])
            ->first();

        if (!$branch || (int) $branch->company_id !== $companyId) {
            throw new RuntimeException(
                'Branch does not belong to warehouse company.'
            );
        }

        return $this->repository->create([
            'company_id' => $companyId,
            'branch_id'  => $branch->id,
            'code'       => $data['code'],
            'name'       => $data['name'],
            'phone'      => $data['phone'] ?? null,
            'email'      => $data['email'] ?? null,
            'address'    => $data['address'] ?? null,
            'is_active'  => $data['is_active'] ?? true,
        ]);
    }

    public function update(
        int $id,
        int $companyId,
        array $data
    ): bool {
        $warehouse = $this->repository->find(
            $id,
            $companyId
        );

        if (!$warehouse) {
            return false;
        }

        $branchId = (int) (
            $data['branch_id']
            ?? $warehouse->branch_id
        );

        $branch = Branch::query()
            ->whereKey($branchId)
            ->first();

        if (!$branch || (int) $branch->company_id !== $companyId) {
            throw new RuntimeException(
                'Branch does not belong to warehouse company.'
            );
        }

        /*
         * Ownership is immutable.
         * company_id is deliberately never accepted from $data.
         */
        $payload = [
            'branch_id' => $branch->id,
        ];

        foreach ([
            'code',
            'name',
            'phone',
            'email',
            'address',
            'is_active',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        return $this->repository->update(
            $id,
            $companyId,
            $payload
        );
    }

    public function delete(
        int $id,
        int $companyId
    ): bool {
        return $this->repository->delete(
            $id,
            $companyId
        );
    }
}
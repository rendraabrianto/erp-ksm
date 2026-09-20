<?php

namespace App\Repositories;

use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;

class CustomerRepository
implements CustomerRepositoryInterface
{
    public function all(
        int $companyId
    ) {
        return Customer::query()
            ->where(
                'company_id',
                $companyId
            )
            ->latest()
            ->paginate(20);
    }

    public function find(
        int $id,
        int $companyId
    ) {
        return Customer::query()
            ->where(
                'company_id',
                $companyId
            )
            ->whereKey(
                $id
            )
            ->firstOrFail();
    }

    public function create(
        array $data
    ) {
        return Customer::create(
            $data
        );
    }

    public function update(
        int $id,
        int $companyId,
        array $data
    ) {
        return $this
            ->find(
                $id,
                $companyId
            )
            ->update(
                $data
            );
    }

    public function delete(
        int $id,
        int $companyId
    ) {
        return $this
            ->find(
                $id,
                $companyId
            )
            ->delete();
    }
}
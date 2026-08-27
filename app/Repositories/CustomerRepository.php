<?php

namespace App\Repositories;

use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;

class CustomerRepository
implements CustomerRepositoryInterface
{
    public function all()
    {
        return Customer::latest()
            ->paginate(20);
    }

    public function find(
        int $id
    ) {
        return Customer::findOrFail(
            $id
        );
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
        array $data
    ) {
        return $this->find($id)
            ->update($data);
    }

    public function delete(
        int $id
    ) {
        return $this->find($id)
            ->delete();
    }
}
<?php

namespace App\Repositories\Contracts;

interface AccountPayableRepositoryInterface
{
    public function create(
        array $data
    );

    public function find(
        int $id
    );

    public function update(
        int $id,
        array $data
    );

    public function paginate(
        int $perPage = 15
    );
}
<?php

namespace App\Repositories\Contracts;

interface CustomerRepositoryInterface
{
    public function all(
        int $companyId
    );

    public function find(
        int $id,
        int $companyId
    );

    public function create(
        array $data
    );

    public function update(
        int $id,
        int $companyId,
        array $data
    );

    public function delete(
        int $id,
        int $companyId
    );
}
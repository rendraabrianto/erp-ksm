<?php

namespace App\Repositories\Contracts;

use App\Models\Branch;

interface BranchRepositoryInterface
{
    public function paginate(
        int $companyId,
        int $perPage = 10
    );

    public function find(
        int $companyId,
        int $id
    ): ?Branch;

    public function create(
        int $companyId,
        array $data
    ): Branch;

    public function update(
        int $companyId,
        Branch $branch,
        array $data
    ): bool;
}
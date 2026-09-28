<?php

namespace App\Repositories\Contracts;

use App\Models\User;

interface UserRepositoryInterface
{
    public function paginate(
        int $companyId,
        int $perPage = 10
    );

    public function find(
        int $companyId,
        int $id
    ): ?User;

    public function create(
        int $companyId,
        array $data
    ): User;

    public function update(
        int $companyId,
        User $user,
        array $data
    ): bool;

    public function delete(
        int $companyId,
        User $user
    ): bool;
}
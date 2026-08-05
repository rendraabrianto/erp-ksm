<?php

namespace App\Services;

use App\Repositories\Contracts\RoleRepositoryInterface;

class RoleService
{
    public function __construct(
        private RoleRepositoryInterface $repository
    ) {}

    public function paginate()
    {
        return $this->repository->paginate();
    }
}
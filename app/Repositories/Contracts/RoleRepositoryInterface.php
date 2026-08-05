<?php

namespace App\Repositories\Contracts;

interface RoleRepositoryInterface
{
    public function paginate(int $perPage = 10);
}
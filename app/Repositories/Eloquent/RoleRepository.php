<?php

namespace App\Repositories\Eloquent;

use Spatie\Permission\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;

class RoleRepository implements RoleRepositoryInterface
{
    public function paginate(int $perPage = 10)
    {
        return Role::withCount('users')
            ->orderBy('name')
            ->paginate($perPage);
    }
}
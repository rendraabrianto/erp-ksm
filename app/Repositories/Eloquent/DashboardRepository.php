<?php

namespace App\Repositories\Eloquent;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use Spatie\Permission\Models\Role;

class DashboardRepository implements DashboardRepositoryInterface
{
    public function getStatistics(): array
    {
        return [
            'companies' => Company::count(),
            'branches'  => Branch::count(),
            'users'     => User::count(),
            'roles'     => Role::count(),
        ];
    }
}
<?php

namespace App\Repositories\Eloquent;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use Spatie\Permission\Models\Role;

class DashboardRepository implements DashboardRepositoryInterface
{
    public function getStatistics(
        int $companyId
    ): array {
        return [
            'companies' => Company::query()
                ->whereKey($companyId)
                ->count(),

            'branches' => Branch::query()
                ->where('company_id', $companyId)
                ->count(),

            'users' => User::query()
                ->where('company_id', $companyId)
                ->count(),

            /*
             * Roles are global authorization configuration.
             * They are not company-owned master data.
             */
            'roles' => Role::count(),
        ];
    }
}
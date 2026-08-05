<?php

namespace App\Services;

use App\DTO\DashboardData;
use App\Repositories\Contracts\DashboardRepositoryInterface;

class DashboardService
{
    public function __construct(
        private DashboardRepositoryInterface $repository
    ) {}

    public function getDashboardData(): DashboardData
    {
        $data = $this->repository->getStatistics();

        return new DashboardData(
            companies: $data['companies'],
            branches: $data['branches'],
            users: $data['users'],
            roles: $data['roles'],
        );
    }
}
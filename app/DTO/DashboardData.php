<?php

namespace App\DTO;

class DashboardData
{
    public function __construct(
        public int $companies,
        public int $branches,
        public int $users,
        public int $roles,
    ) {}
}
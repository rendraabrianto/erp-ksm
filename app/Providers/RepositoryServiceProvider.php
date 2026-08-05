<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Repositories\Contracts\DashboardRepositoryInterface;
use App\Repositories\Eloquent\DashboardRepository;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Eloquent\CompanyRepository;

use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\UserRepository;

use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Repositories\Eloquent\BranchRepository;

use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Eloquent\RoleRepository;

use App\Repositories\Contracts\WarehouseRepositoryInterface;
use App\Repositories\Eloquent\WarehouseRepository;

use App\Repositories\Contracts\JournalRepositoryInterface;
use App\Repositories\Eloquent\JournalRepository;

use App\Repositories\Contracts\DocumentSequenceRepositoryInterface;
use App\Repositories\Eloquent\DocumentSequenceRepository;

use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Eloquent\AuditLogRepository;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            DashboardRepositoryInterface::class,
            DashboardRepository::class
        );
        $this->app->bind(
            CompanyRepositoryInterface::class,
            CompanyRepository::class
        );
        $this->app->bind(
            UserRepositoryInterface::class,
            UserRepository::class
        );
        $this->app->bind(
            BranchRepositoryInterface::class,
            BranchRepository::class
        );
        $this->app->bind(
            RoleRepositoryInterface::class,
            RoleRepository::class
        );
        $this->app->bind(
            WarehouseRepositoryInterface::class,
            WarehouseRepository::class
        );        
        $this->app->bind(
            JournalRepositoryInterface::class,
            JournalRepository::class
        );
        $this->app->bind(
            DocumentSequenceRepositoryInterface::class,
            DocumentSequenceRepository::class
        );
        $this->app->bind(
            AuditLogRepositoryInterface::class,
            AuditLogRepository::class
        );
    }
}
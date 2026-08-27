<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CompanySeeder::class,
            BranchSeeder::class,
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            SuperAdminSeeder::class,
            WarehouseSeeder::class,
            UomSeeder::class,
            ItemCategorySeeder::class,
            AccountGroupSeeder::class,
            AccountSeeder::class,
            DocumentSequenceSeeder::class,
        ]);
    }
}
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'inventory.reconciliation.view',
            'inventory.reconciliation.preview',
            'inventory.reconciliation.apply',
            'inventory.current-stock.view',
            'inventory.stock-ledger.view',
            'inventory.valuation.view',
            'inventory.adjustment.view',
            'inventory.adjustment.create',
            'inventory.adjustment.post',
            'accounting.journal.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }
    }
}
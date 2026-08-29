<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | SUPER ADMIN
        |--------------------------------------------------------------------------
        */

        $superAdmin = Role::where(
            'name',
            'Super Admin'
        )
            ->where(
                'guard_name',
                'web'
            )
            ->firstOrFail();

        $superAdminPermissions = Permission::whereIn(
            'name',
            [
                'inventory.reconciliation.view',
                'inventory.reconciliation.preview',
                'inventory.reconciliation.apply',
                'inventory.current-stock.view',
                'inventory.stock-ledger.view',
                'inventory.valuation.view',
                'inventory.adjustment.view',
                'inventory.adjustment.create',
                'inventory.adjustment.post',
                'inventory.transfer.view',
                'inventory.transfer.create',
                'inventory.transfer.post',
                'accounting.journal.view',
            ]
        )->get();

        $superAdmin->syncPermissions(
            $superAdminPermissions
        );

        /*
        |--------------------------------------------------------------------------
        | ACCOUNTING MANAGER
        |--------------------------------------------------------------------------
        */

        $accountingManager = Role::where(
            'name',
            'Accounting Manager'
        )
            ->where(
                'guard_name',
                'web'
            )
            ->first();

        if ($accountingManager) {

            $accountingManager->syncPermissions(
                Permission::whereIn(
                    'name',
                    [
                    'inventory.reconciliation.view',
                    'inventory.reconciliation.preview',

                    'inventory.current-stock.view',
                    'inventory.stock-ledger.view',
                    'inventory.valuation.view',

                    'inventory.adjustment.view',
                    'inventory.adjustment.create',
                    'inventory.adjustment.post',

                    'inventory.transfer.view',
                    'inventory.transfer.create',
                    'inventory.transfer.post',

                    'accounting.journal.view',
                ]
                )->get()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | INVENTORY MANAGER
        |--------------------------------------------------------------------------
        */

        $inventoryManager = Role::where(
            'name',
            'Inventory Manager'
        )
            ->where(
                'guard_name',
                'web'
            )
            ->first();

        if ($inventoryManager) {

            $inventoryManager->syncPermissions(
                Permission::whereIn(
                    'name',
                    [
                        'inventory.reconciliation.view',
                        'inventory.reconciliation.preview',

                        'inventory.current-stock.view',
                        'inventory.stock-ledger.view',
                        'inventory.valuation.view',

                        'inventory.adjustment.view',
                        'inventory.adjustment.create',
                        'inventory.adjustment.post',

                        'inventory.transfer.view',
                        'inventory.transfer.create',
                        'inventory.transfer.post',

                        'accounting.journal.view',
                    ]
                )->get()
            );
        }
    }
}
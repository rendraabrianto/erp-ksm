<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;


return new class extends Migration
{
    /**
     * Tables whose company ownership must be mandatory.
     */
    private array $tables = [
        'purchase_requests',
        'purchase_orders',
        'goods_receipts',
        'purchase_invoices',
        'payment_vouchers',

        'sales_orders',
        'delivery_orders',
        'sales_invoices',
        'customer_receipts',

        'account_payables',
        'account_receivables',

        'inventory_adjustments',
        'inventory_transfers',

        'journals',
        'stock_ledgers',
    ];

    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Preflight Validation
        |--------------------------------------------------------------------------
        |
        | Never silently assign company ownership here.
        | Legacy data must already have been backfilled correctly.
        |
        */

        foreach ($this->tables as $table) {
            $nullCount =
                DB::table($table)
                    ->whereNull('company_id')
                    ->count();

            if ($nullCount > 0) {
                throw new \RuntimeException(
                    sprintf(
                        'Cannot enforce company_id NOT NULL on %s: %d row(s) still have NULL company_id.',
                        $table,
                        $nullCount
                    )
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Enforce Mandatory Company Ownership
        |--------------------------------------------------------------------------
        */

        foreach ($this->tables as $table) {
            DB::statement(
                sprintf(
                    'ALTER TABLE `%s` MODIFY `company_id` BIGINT UNSIGNED NOT NULL',
                    $table
                )
            );
        }
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Rollback Schema Constraint Only
        |--------------------------------------------------------------------------
        |
        | Existing company_id values are preserved.
        |
        */

        foreach (array_reverse($this->tables) as $table) {
            DB::statement(
                sprintf(
                    'ALTER TABLE `%s` MODIFY `company_id` BIGINT UNSIGNED NULL',
                    $table
                )
            );
        }
    }
};
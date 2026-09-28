<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Add Company Ownership
        |--------------------------------------------------------------------------
        */

        Schema::table(
            'inventory_reconciliation_histories',
            function (Blueprint $table) {

                $table
                    ->unsignedBigInteger('company_id')
                    ->nullable()
                    ->after('id');

                $table->index(
                    'company_id',
                    'inv_rec_hist_company_idx'
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Preflight Warehouse Ownership
        |--------------------------------------------------------------------------
        |
        | Warehouse is the authoritative source for historical company ownership.
        | Every existing reconciliation history must reference a valid warehouse
        | whose company_id is already populated.
        |
        */

        $invalidWarehouse =
            DB::table('inventory_reconciliation_histories as h')
                ->leftJoin(
                    'warehouses as w',
                    'w.id',
                    '=',
                    'h.warehouse_id'
                )
                ->where(function ($query) {
                    $query
                        ->whereNull('w.id')
                        ->orWhereNull('w.company_id');
                })
                ->exists();

        if ($invalidWarehouse) {
            throw new \RuntimeException(
                'Cannot backfill reconciliation history company ownership: invalid warehouse ownership exists.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Deterministic Backfill
        |--------------------------------------------------------------------------
        */

        DB::statement(
            '
            UPDATE inventory_reconciliation_histories h
            INNER JOIN warehouses w
                ON w.id = h.warehouse_id
            SET h.company_id = w.company_id
            WHERE h.company_id IS NULL
            '
        );

        /*
        |--------------------------------------------------------------------------
        | Validate Backfill
        |--------------------------------------------------------------------------
        */

        if (
            DB::table('inventory_reconciliation_histories')
                ->whereNull('company_id')
                ->exists()
        ) {
            throw new \RuntimeException(
                'Cannot add reconciliation history company ownership: histories with NULL company_id remain.'
            );
        }

        $orphanCompany =
            DB::table('inventory_reconciliation_histories as h')
                ->leftJoin(
                    'companies as c',
                    'c.id',
                    '=',
                    'h.company_id'
                )
                ->whereNull('c.id')
                ->exists();

        if ($orphanCompany) {
            throw new \RuntimeException(
                'Cannot add reconciliation history company ownership: histories reference missing companies.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Warehouse Company Consistency
        |--------------------------------------------------------------------------
        */

        $warehouseMismatch =
            DB::table('inventory_reconciliation_histories as h')
                ->join(
                    'warehouses as w',
                    'w.id',
                    '=',
                    'h.warehouse_id'
                )
                ->whereColumn(
                    'h.company_id',
                    '!=',
                    'w.company_id'
                )
                ->exists();

        if ($warehouseMismatch) {
            throw new \RuntimeException(
                'Cannot add reconciliation history company ownership: warehouse company mismatch exists.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Item Company Consistency
        |--------------------------------------------------------------------------
        */

        $invalidItem =
            DB::table('inventory_reconciliation_histories as h')
                ->leftJoin(
                    'items as i',
                    'i.id',
                    '=',
                    'h.item_id'
                )
                ->where(function ($query) {
                    $query
                        ->whereNull('i.id')
                        ->orWhereNull('i.company_id');
                })
                ->exists();

        if ($invalidItem) {
            throw new \RuntimeException(
                'Cannot add reconciliation history company ownership: invalid item ownership exists.'
            );
        }

        $itemMismatch =
            DB::table('inventory_reconciliation_histories as h')
                ->join(
                    'items as i',
                    'i.id',
                    '=',
                    'h.item_id'
                )
                ->whereColumn(
                    'h.company_id',
                    '!=',
                    'i.company_id'
                )
                ->exists();

        if ($itemMismatch) {
            throw new \RuntimeException(
                'Cannot add reconciliation history company ownership: item company mismatch exists.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Foreign Key
        |--------------------------------------------------------------------------
        */

        Schema::table(
            'inventory_reconciliation_histories',
            function (Blueprint $table) {

                $table
                    ->foreign('company_id')
                    ->references('id')
                    ->on('companies')
                    ->restrictOnDelete();
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Enforce NOT NULL
        |--------------------------------------------------------------------------
        */

        DB::statement(
            '
            ALTER TABLE inventory_reconciliation_histories
            MODIFY company_id BIGINT UNSIGNED NOT NULL
            '
        );

        /*
        |--------------------------------------------------------------------------
        | Company-Scoped History Lookup
        |--------------------------------------------------------------------------
        */

        Schema::table(
            'inventory_reconciliation_histories',
            function (Blueprint $table) {

                $table->index(
                    [
                        'company_id',
                        'warehouse_id',
                        'item_id',
                        'date_from',
                        'date_to',
                    ],
                    'inv_rec_hist_company_scope_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'inventory_reconciliation_histories',
            function (Blueprint $table) {

                $table->dropIndex(
                    'inv_rec_hist_company_scope_idx'
                );

                $table->dropForeign([
                    'company_id',
                ]);

                $table->dropIndex(
                    'inv_rec_hist_company_idx'
                );

                $table->dropColumn(
                    'company_id'
                );
            }
        );
    }
};
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
        | Step 1 - Add Nullable Company Ownership
        |--------------------------------------------------------------------------
        */

        Schema::table(
            'stock_ledgers',
            function (Blueprint $table) {
                $table
                    ->unsignedBigInteger('company_id')
                    ->nullable()
                    ->after('id')
                    ->index();
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Step 2 - Backfill From Warehouse
        |--------------------------------------------------------------------------
        |
        | Historical ledger ownership follows the warehouse that owns
        | the inventory.
        |
        */

        DB::statement("
            UPDATE stock_ledgers sl
            INNER JOIN warehouses w
                ON w.id = sl.warehouse_id
            SET sl.company_id = w.company_id
            WHERE sl.company_id IS NULL
        ");

        /*
        |--------------------------------------------------------------------------
        | Step 3 - Validate Historical Backfill
        |--------------------------------------------------------------------------
        */

        $nullCount =
            DB::table('stock_ledgers')
                ->whereNull('company_id')
                ->count();

        if ($nullCount > 0) {
            throw new \RuntimeException(
                sprintf(
                    'Unable to backfill company_id for %d stock ledger row(s).',
                    $nullCount
                )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Step 4 - Foreign Key
        |--------------------------------------------------------------------------
        |
        | Do NOT cascade-delete historical inventory ledger.
        |
        */

        Schema::table(
            'stock_ledgers',
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
        | NOT NULL intentionally deferred to Phase G7
        |--------------------------------------------------------------------------
        */
    }

    public function down(): void
    {
        Schema::table(
            'stock_ledgers',
            function (Blueprint $table) {
                $table->dropForeign([
                    'company_id',
                ]);

                $table->dropColumn(
                    'company_id'
                );
            }
        );
    }
};
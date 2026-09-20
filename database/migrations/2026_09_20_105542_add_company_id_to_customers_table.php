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

        Schema::table('customers', function (Blueprint $table) {
            $table
                ->unsignedBigInteger('company_id')
                ->nullable()
                ->after('id');

            $table->index(
                'company_id',
                'customers_company_id_index'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Deterministic Backfill
        |--------------------------------------------------------------------------
        |
        | Customer lama tidak memiliki informasi company.
        |
        | Backfill hanya aman apabila database memiliki tepat satu company.
        | Jika terdapat lebih dari satu company dan customer lama tersedia,
        | ownership tidak boleh ditebak.
        |
        */

        $customerCount =
            DB::table('customers')
                ->count();

        $companyIds =
            DB::table('companies')
                ->orderBy('id')
                ->pluck('id');

        if ($customerCount > 0) {
            if ($companyIds->count() !== 1) {
                throw new \RuntimeException(
                    'Cannot deterministically backfill customers.company_id: existing customers require exactly one company.'
                );
            }

            DB::table('customers')
                ->whereNull('company_id')
                ->update([
                    'company_id' =>
                        (int) $companyIds->first(),
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Backfill
        |--------------------------------------------------------------------------
        */

        $nullCompanyCount =
            DB::table('customers')
                ->whereNull('company_id')
                ->count();

        if ($nullCompanyCount > 0) {
            throw new \RuntimeException(
                'Cannot add customer company ownership: customers with NULL company_id remain.'
            );
        }

        $orphanCompanyCount =
            DB::table('customers as c')
                ->leftJoin(
                    'companies as co',
                    'co.id',
                    '=',
                    'c.company_id'
                )
                ->whereNotNull('c.company_id')
                ->whereNull('co.id')
                ->count();

        if ($orphanCompanyCount > 0) {
            throw new \RuntimeException(
                'Cannot add customer company ownership: customers reference missing companies.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Foreign Key
        |--------------------------------------------------------------------------
        */

        Schema::table('customers', function (Blueprint $table) {
            $table
                ->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->restrictOnDelete();
        });

        /*
        |--------------------------------------------------------------------------
        | Company-Scoped Customer Code
        |--------------------------------------------------------------------------
        |
        | Sebelumnya:
        |
        |     UNIQUE(code)
        |
        | Menjadi:
        |
        |     UNIQUE(company_id, code)
        |
        | Dengan demikian dua company boleh menggunakan customer code yang sama.
        |
        */

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(
                'customers_code_unique'
            );

            $table->unique(
                [
                    'company_id',
                    'code',
                ],
                'customers_company_code_unique'
            );
        });
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Preflight Global Code Uniqueness
        |--------------------------------------------------------------------------
        |
        | Rollback akan mengembalikan UNIQUE(code).
        | Maka rollback harus ditolak apabila code yang sama sudah digunakan
        | oleh lebih dari satu company.
        |
        */

        $duplicateCode =
            DB::table('customers')
                ->select(
                    'code',
                    DB::raw('COUNT(*) as total')
                )
                ->groupBy('code')
                ->havingRaw('COUNT(*) > 1')
                ->first();

        if ($duplicateCode !== null) {
            throw new \RuntimeException(
                'Cannot rollback customer company isolation: duplicate customer codes exist across companies.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Restore Global Unique Code
        |--------------------------------------------------------------------------
        */

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(
                'customers_company_code_unique'
            );

            $table->unique(
                'code',
                'customers_code_unique'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Remove Company Ownership
        |--------------------------------------------------------------------------
        */

        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign([
                'company_id',
            ]);

            $table->dropIndex(
                'customers_company_id_index'
            );

            $table->dropColumn(
                'company_id'
            );
        });
    }
};
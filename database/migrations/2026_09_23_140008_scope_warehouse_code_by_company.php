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
         * Safety check:
         * company_id must already be populated.
         */
        if (
            DB::table('warehouses')
                ->whereNull('company_id')
                ->exists()
        ) {
            throw new \RuntimeException(
                'Cannot scope warehouse codes by company: warehouses with NULL company_id exist.'
            );
        }

        /*
         * Safety check:
         * company_id must reference an existing company.
         */
        $hasOrphanCompany = DB::table('warehouses as w')
            ->leftJoin(
                'companies as c',
                'c.id',
                '=',
                'w.company_id'
            )
            ->whereNull('c.id')
            ->exists();

        if ($hasOrphanCompany) {
            throw new \RuntimeException(
                'Cannot scope warehouse codes by company: warehouses reference missing companies.'
            );
        }

        /*
         * Safety check:
         * no duplicate warehouse code may exist inside
         * the same company.
         */
        $duplicate = DB::table('warehouses')
            ->select(
                'company_id',
                'code',
                DB::raw('COUNT(*) as duplicate_count')
            )
            ->groupBy(
                'company_id',
                'code'
            )
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate) {
            throw new \RuntimeException(
                sprintf(
                    'Cannot scope warehouse codes by company: duplicate warehouse code %s exists for company %d.',
                    $duplicate->code,
                    $duplicate->company_id
                )
            );
        }

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropUnique(
                'warehouses_code_unique'
            );

            $table->unique(
                ['company_id', 'code'],
                'warehouses_company_code_unique'
            );
        });
    }

    public function down(): void
    {
        /*
         * Rolling back to UNIQUE(code) is unsafe when two
         * different companies already use the same code.
         */
        $duplicate = DB::table('warehouses')
            ->select(
                'code',
                DB::raw('COUNT(*) as duplicate_count')
            )
            ->groupBy('code')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate) {
            throw new \RuntimeException(
                sprintf(
                    'Cannot rollback warehouse company isolation: warehouse code %s exists in multiple companies.',
                    $duplicate->code
                )
            );
        }

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropUnique(
                'warehouses_company_code_unique'
            );

            $table->unique(
                'code',
                'warehouses_code_unique'
            );
        });
    }
};
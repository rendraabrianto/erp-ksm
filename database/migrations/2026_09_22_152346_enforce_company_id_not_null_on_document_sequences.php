<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Preflight - NULL Company Ownership
        |--------------------------------------------------------------------------
        */

        $nullCompanyCount =
            DB::table('document_sequences')
                ->whereNull('company_id')
                ->count();

        if ($nullCompanyCount > 0) {
            throw new RuntimeException(
                'Cannot enforce document_sequences.company_id NOT NULL: document sequences with NULL company_id remain.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Preflight - Orphan Company Ownership
        |--------------------------------------------------------------------------
        */

        $orphanCompanyCount =
            DB::table('document_sequences as ds')
                ->leftJoin(
                    'companies as c',
                    'c.id',
                    '=',
                    'ds.company_id'
                )
                ->whereNotNull('ds.company_id')
                ->whereNull('c.id')
                ->count();

        if ($orphanCompanyCount > 0) {
            throw new RuntimeException(
                'Cannot enforce document_sequences.company_id NOT NULL: document sequences reference missing companies.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Preflight - Duplicate Company / Document Type
        |--------------------------------------------------------------------------
        */

        $duplicate =
            DB::table('document_sequences')
                ->select(
                    'company_id',
                    'document_type',
                    DB::raw('COUNT(*) AS total')
                )
                ->groupBy(
                    'company_id',
                    'document_type'
                )
                ->havingRaw(
                    'COUNT(*) > 1'
                )
                ->first();

        if ($duplicate) {
            throw new RuntimeException(
                sprintf(
                    'Cannot enforce document_sequences.company_id NOT NULL: duplicate document type %s exists for company %s.',
                    $duplicate->document_type,
                    $duplicate->company_id
                )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Enforce Company Ownership
        |--------------------------------------------------------------------------
        */

        Schema::table(
            'document_sequences',
            function (Blueprint $table) {
                $table
                    ->unsignedBigInteger('company_id')
                    ->nullable(false)
                    ->change();
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(
            'document_sequences',
            function (Blueprint $table) {
                $table
                    ->unsignedBigInteger('company_id')
                    ->nullable()
                    ->change();
            }
        );
    }
};
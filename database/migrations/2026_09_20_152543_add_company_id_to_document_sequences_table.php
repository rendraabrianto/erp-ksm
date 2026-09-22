<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_sequences', function (Blueprint $table) {
            $table
                ->unsignedBigInteger('company_id')
                ->nullable()
                ->after('id');

            $table->index(
                'company_id',
                'document_sequences_company_id_index'
            );
        });

        $sequenceCount =
            DB::table('document_sequences')->count();

        if ($sequenceCount > 0) {
            $companyCount =
                DB::table('companies')->count();

            if ($companyCount !== 1) {
                throw new \RuntimeException(
                    'Cannot deterministically backfill document_sequences.company_id: existing sequences require exactly one company.'
                );
            }

            $companyId =
                DB::table('companies')->value('id');

            DB::table('document_sequences')
                ->whereNull('company_id')
                ->update([
                    'company_id' => $companyId,
                ]);
        }

        $nullCount =
            DB::table('document_sequences')
                ->whereNull('company_id')
                ->count();

        if ($nullCount > 0) {
            throw new \RuntimeException(
                'Cannot add document sequence company ownership: sequences with NULL company_id remain.'
            );
        }

        $orphanCount =
            DB::table('document_sequences as ds')
                ->leftJoin(
                    'companies as c',
                    'c.id',
                    '=',
                    'ds.company_id'
                )
                ->whereNull('c.id')
                ->count();

        if ($orphanCount > 0) {
            throw new \RuntimeException(
                'Cannot add document sequence company ownership: sequences reference missing companies.'
            );
        }

        Schema::table('document_sequences', function (Blueprint $table) {
            $table
                ->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->restrictOnDelete();

            $table->dropUnique(
                'document_sequences_document_type_unique'
            );

            $table->unique(
                ['company_id', 'document_type'],
                'document_sequences_company_document_type_unique'
            );
        });
    }

    public function down(): void
    {
        $duplicateDocumentTypes =
            DB::table('document_sequences')
                ->select(
                    'document_type',
                    DB::raw('COUNT(*) as total')
                )
                ->groupBy('document_type')
                ->havingRaw('COUNT(*) > 1')
                ->count();

        if ($duplicateDocumentTypes > 0) {
            throw new \RuntimeException(
                'Cannot rollback document sequence company isolation: duplicate document types exist across companies.'
            );
        }

        Schema::table('document_sequences', function (Blueprint $table) {
            $table->dropUnique(
                'document_sequences_company_document_type_unique'
            );

            $table->unique(
                'document_type',
                'document_sequences_document_type_unique'
            );

            $table->dropForeign(
                ['company_id']
            );

            $table->dropIndex(
                'document_sequences_company_id_index'
            );

            $table->dropColumn(
                'company_id'
            );
        });
    }
};
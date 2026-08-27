<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journals', function (Blueprint $table) {

            $table->string(
                'journal_purpose',
                30
            )
                ->default('NORMAL')
                ->after('reference_id');

            $table->unsignedBigInteger(
                'source_journal_id'
            )
                ->nullable()
                ->after('journal_purpose');

            $table->string(
                'reconciliation_key',
                191
            )
                ->nullable()
                ->unique()
                ->after('source_journal_id');

            $table->foreign(
                'source_journal_id'
            )
                ->references('id')
                ->on('journals')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journals', function (Blueprint $table) {

            $table->dropForeign([
                'source_journal_id'
            ]);

            $table->dropUnique([
                'reconciliation_key'
            ]);

            $table->dropColumn([
                'journal_purpose',
                'source_journal_id',
                'reconciliation_key',
            ]);
        });
    }
};
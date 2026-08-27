<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'inventory_reconciliation_histories',
            function (Blueprint $table) {

                $table->id();

                $table->foreignId('warehouse_id')
                    ->constrained()
                    ->restrictOnDelete();

                $table->foreignId('item_id')
                    ->constrained()
                    ->restrictOnDelete();

                $table->date('date_from');
                $table->date('date_to');

                $table->unsignedInteger(
                    'missing_before'
                )->default(0);

                $table->unsignedInteger(
                    'mismatch_before'
                )->default(0);

                $table->unsignedInteger(
                    'orphan_before'
                )->default(0);

                $table->unsignedInteger(
                    'recovery_posted'
                )->default(0);

                $table->unsignedInteger(
                    'correction_posted'
                )->default(0);

                $table->unsignedInteger(
                    'reversal_posted'
                )->default(0);

                $table->unsignedInteger(
                    'journal_posted_count'
                )->default(0);

                $table->unsignedInteger(
                    'missing_after'
                )->default(0);

                $table->unsignedInteger(
                    'mismatch_after'
                )->default(0);

                $table->unsignedInteger(
                    'orphan_after'
                )->default(0);

                $table->boolean(
                    'is_reconciled_after'
                )->default(false);

                $table->foreignId('executed_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp(
                    'executed_at'
                );

                $table->timestamps();

                $table->index(
                    [
                        'warehouse_id',
                        'item_id',
                        'date_from',
                        'date_to',
                    ],
                    'inv_rec_hist_scope_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'inventory_reconciliation_histories'
        );
    }
};
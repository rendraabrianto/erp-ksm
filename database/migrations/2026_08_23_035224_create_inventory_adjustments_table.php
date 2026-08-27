<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'inventory_adjustments',
            function (Blueprint $table) {

                $table->id();

                $table->string(
                    'adjustment_no'
                )->unique();

                $table->date(
                    'adjustment_date'
                );

                $table->foreignId(
                    'warehouse_id'
                )
                    ->constrained()
                    ->restrictOnDelete();

                $table->string(
                    'status'
                )->default('DRAFT');

                $table->string(
                    'reason'
                );

                $table->text(
                    'remarks'
                )->nullable();

                $table->foreignId(
                    'created_by'
                )
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->foreignId(
                    'posted_by'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp(
                    'posted_at'
                )->nullable();

                $table->timestamps();

                $table->index([
                    'warehouse_id',
                    'adjustment_date',
                    'status',
                ], 'inv_adj_wh_date_status_idx');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'inventory_adjustments'
        );
    }
};
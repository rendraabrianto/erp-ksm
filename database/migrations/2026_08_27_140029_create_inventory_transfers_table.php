<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transfers', function (Blueprint $table) {
            $table->id();

            $table->string('transfer_no')->unique();
            $table->date('transfer_date');

            $table->foreignId('source_warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete();

            $table->foreignId('destination_warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete();

            $table->string('status', 20)
                ->default('DRAFT');

            $table->text('remarks')->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('posted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('posted_at')->nullable();

            $table->timestamps();

            $table->index([
                'transfer_date',
                'status',
            ]);

            $table->index('source_warehouse_id');
            $table->index('destination_warehouse_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transfers');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'inventory_transfer_details',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('inventory_transfer_id')
                    ->constrained('inventory_transfers')
                    ->cascadeOnDelete();

                $table->foreignId('item_id')
                    ->constrained('items')
                    ->restrictOnDelete();

                $table->decimal(
                    'qty',
                    18,
                    4
                );

                $table->decimal(
                    'unit_cost',
                    18,
                    2
                )->default(0);

                $table->decimal(
                    'total_cost',
                    18,
                    2
                )->default(0);

                $table->text('remarks')->nullable();

                $table->timestamps();

                $table->unique([
                    'inventory_transfer_id',
                    'item_id',
                ]);

                $table->index('item_id');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'inventory_transfer_details'
        );
    }
};
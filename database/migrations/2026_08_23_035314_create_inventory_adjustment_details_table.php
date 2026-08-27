<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'inventory_adjustment_details',
            function (Blueprint $table) {

                $table->id();

                $table->foreignId(
                    'inventory_adjustment_id'
                )
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId(
                    'item_id'
                )
                    ->constrained()
                    ->restrictOnDelete();

                /*
                | Qty menurut sistem saat posting.
                */
                $table->decimal(
                    'system_qty',
                    18,
                    4
                )->default(0);

                /*
                | Hasil stock opname / physical count.
                */
                $table->decimal(
                    'physical_qty',
                    18,
                    4
                );

                /*
                | physical_qty - system_qty
                |
                | positif = IN
                | negatif = OUT
                */
                $table->decimal(
                    'adjustment_qty',
                    18,
                    4
                )->default(0);

                /*
                | Moving average saat posting.
                */
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

                $table->string(
                    'remarks'
                )->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'inventory_adjustment_id',
                        'item_id',
                    ],
                    'inv_adj_detail_item_uq'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'inventory_adjustment_details'
        );
    }
};
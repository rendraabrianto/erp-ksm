<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'purchase_order_details',
            function (Blueprint $table) {

                $table->id();

                $table->foreignId(
                    'purchase_order_id'
                )
                ->constrained()
                ->cascadeOnDelete();

                $table->foreignId(
                    'item_id'
                )
                ->constrained()
                ->cascadeOnDelete();

                $table->decimal(
                    'qty',
                    18,
                    4
                );

                $table->decimal(
                    'unit_price',
                    18,
                    2
                )->default(0);

                $table->decimal(
                    'received_qty',
                    18,
                    4
                )->default(0);

                $table->text('remarks')
                    ->nullable();

                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'purchase_order_details'
        );
    }
};
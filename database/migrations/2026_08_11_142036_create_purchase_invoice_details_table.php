<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'purchase_invoice_details',
            function (Blueprint $table) {

                $table->id();

                $table->foreignId(
                    'purchase_invoice_id'
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
                );

                $table->decimal(
                    'amount',
                    18,
                    2
                );

                $table->text(
                    'remarks'
                )->nullable();

                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'purchase_invoice_details'
        );
    }
};
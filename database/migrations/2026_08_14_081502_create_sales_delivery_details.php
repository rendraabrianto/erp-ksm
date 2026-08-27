<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sales_delivery_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_delivery_id');
            $table->foreignId('sales_order_detail_id');
            $table->foreignId('item_id');
            $table->decimal('qty_delivered',18,4);
            $table->decimal('unit_cost',18,2);
            $table->text('remarks')
                ->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_delivery_details');
    }
};

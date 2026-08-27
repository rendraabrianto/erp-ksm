<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {

            $table->id();

            $table->foreignId('item_category_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('uom_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('code',30)
                ->unique();

            $table->string('name',150);

            $table->text('description')
                ->nullable();

            $table->decimal(
                'minimum_stock',
                18,
                4
            )->default(0);

            $table->decimal(
                'maximum_stock',
                18,
                4
            )->default(0);

            $table->decimal(
                'average_cost',
                18,
                2
            )->default(0);

            $table->decimal(
                'last_purchase_price',
                18,
                2
            )->default(0);

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index('item_category_id');
            $table->index('uom_id');
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
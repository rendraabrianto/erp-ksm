<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_ledgers', function (Blueprint $table) {

            $table->id();

            $table->foreignId('warehouse_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('item_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->date('transaction_date');

            $table->string('reference_type',100);

            $table->unsignedBigInteger('reference_id');

            $table->decimal(
                'qty_in',
                18,
                4
            )->default(0);

            $table->decimal(
                'qty_out',
                18,
                4
            )->default(0);

            $table->decimal(
                'balance_qty',
                18,
                4
            )->default(0);

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

            $table->text('remarks')
                ->nullable();

            $table->timestamps();

            $table->index([
                'warehouse_id',
                'item_id'
            ]);

            $table->index('transaction_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'stock_ledgers'
        );
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'purchase_invoices',
            function (Blueprint $table) {

                $table->id();

                $table->string(
                    'invoice_no',
                    50
                )->unique();

                $table->date(
                    'invoice_date'
                );

                $table->foreignId(
                    'goods_receipt_id'
                )
                ->constrained()
                ->cascadeOnDelete();

                $table->string(
                    'supplier_name',
                    150
                );

                $table->string(
                    'supplier_invoice_no',
                    100
                )->nullable();

                $table->decimal(
                    'subtotal',
                    18,
                    2
                )->default(0);

                $table->decimal(
                    'tax_amount',
                    18,
                    2
                )->default(0);

                $table->decimal(
                    'grand_total',
                    18,
                    2
                )->default(0);

                $table->string(
                    'status',
                    20
                )->default('POSTED');

                $table->foreignId(
                    'created_by'
                )
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

                $table->timestamps();
                $table->softDeletes();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'purchase_invoices'
        );
    }
};
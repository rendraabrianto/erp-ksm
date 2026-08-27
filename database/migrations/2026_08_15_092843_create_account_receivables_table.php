<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_receivables', function (Blueprint $table) {

            $table->id();

            $table->foreignId('customer_id');

            $table->foreignId('sales_invoice_id');

            $table->date('invoice_date');

            $table->date('due_date');

            $table->decimal(
                'amount',
                18,
                2
            );

            $table->decimal(
                'paid_amount',
                18,
                2
            )->default(0);

            $table->decimal(
                'balance_amount',
                18,
                2
            );

            $table->enum(
                'status',
                [
                    'OPEN',
                    'PARTIAL',
                    'PAID'
                ]
            )->default('OPEN');

            $table->text('remarks')
                ->nullable();

            $table->timestamps();

            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'account_receivables'
        );
    }
};
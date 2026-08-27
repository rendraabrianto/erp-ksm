<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no')->unique();
            $table->foreignId('customer_id');
            $table->foreignId('account_receivable_id');
            $table->foreignId('cash_bank_account_id');
            $table->date('receipt_date');
            $table->decimal(
                'amount',
                18,
                2
            );
            $table->text('remarks')->nullable();
            $table->foreignId('created_by');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'customer_receipts'
        );
    }
};
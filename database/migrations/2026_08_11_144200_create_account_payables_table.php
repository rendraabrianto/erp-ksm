<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'account_payables',
            function (Blueprint $table) {

                $table->id();

                $table->string(
                    'reference_type',
                    100
                );

                $table->unsignedBigInteger(
                    'reference_id'
                );

                $table->string(
                    'supplier_name',
                    200
                );

                $table->date(
                    'invoice_date'
                );

                $table->date(
                    'due_date'
                );

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

                $table->string(
                    'status',
                    30
                )->default('OPEN');

                $table->timestamps();

                $table->index([
                    'reference_type',
                    'reference_id'
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'account_payables'
        );
    }
};
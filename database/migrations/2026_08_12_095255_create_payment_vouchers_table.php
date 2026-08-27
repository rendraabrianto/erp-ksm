<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'payment_vouchers',
            function (Blueprint $table) {

                $table->id();

                $table->string(
                    'voucher_no',
                    50
                )->unique();

                $table->date(
                    'voucher_date'
                );

                $table->foreignId(
                    'account_payable_id'
                )
                ->constrained()
                ->cascadeOnDelete();

                $table->foreignId(
                    'cash_bank_account_id'
                )
                ->constrained(
                    'accounts'
                );

                $table->decimal(
                    'amount',
                    18,
                    2
                );

                $table->string(
                    'payment_method',
                    30
                );

                $table->text(
                    'remarks'
                )->nullable();

                $table->foreignId(
                    'created_by'
                )
                ->nullable()
                ->constrained(
                    'users'
                )
                ->nullOnDelete();

                $table->timestamps();
                $table->softDeletes();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'payment_vouchers'
        );
    }
};
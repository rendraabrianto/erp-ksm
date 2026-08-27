<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'item_categories',
            function (Blueprint $table) {

                $table->foreignId(
                    'adjustment_gain_account_id'
                )
                    ->nullable()
                    ->after('sales_account_id')
                    ->constrained('accounts')
                    ->restrictOnDelete();

                $table->foreignId(
                    'adjustment_loss_account_id'
                )
                    ->nullable()
                    ->after('adjustment_gain_account_id')
                    ->constrained('accounts')
                    ->restrictOnDelete();
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'item_categories',
            function (Blueprint $table) {

                $table->dropConstrainedForeignId(
                    'adjustment_gain_account_id'
                );

                $table->dropConstrainedForeignId(
                    'adjustment_loss_account_id'
                );
            }
        );
    }
};
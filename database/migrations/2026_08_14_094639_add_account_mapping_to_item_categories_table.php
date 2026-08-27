<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_categories', function (Blueprint $table) {

            $table->foreignId('inventory_account_id')
                ->nullable()
                ->constrained('accounts');

            $table->foreignId('cogs_account_id')
                ->nullable()
                ->constrained('accounts');

            $table->foreignId('sales_account_id')
                ->nullable()
                ->constrained('accounts');
        });
    }

    public function down(): void
    {
        Schema::table('item_categories', function (Blueprint $table) {

            $table->dropForeign(['inventory_account_id']);
            $table->dropForeign(['cogs_account_id']);
            $table->dropForeign(['sales_account_id']);

            $table->dropColumn([
                'inventory_account_id',
                'cogs_account_id',
                'sales_account_id'
            ]);
        });
    }
};
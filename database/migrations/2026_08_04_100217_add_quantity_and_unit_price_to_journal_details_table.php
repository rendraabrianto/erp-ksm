<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_details', function (Blueprint $table) {

            $table->decimal(
                'quantity',
                18,
                4
            )->default(0)
             ->after('account_id');

            $table->decimal(
                'unit_price',
                18,
                2
            )->default(0)
             ->after('quantity');

        });
    }

    public function down(): void
    {
        Schema::table('journal_details', function (Blueprint $table) {

            $table->dropColumn([
                'quantity',
                'unit_price',
            ]);

        });
    }
};
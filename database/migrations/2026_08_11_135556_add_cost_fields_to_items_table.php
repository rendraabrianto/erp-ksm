<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'items',
            function (Blueprint $table) {

                if (!Schema::hasColumn(
                    'items',
                    'average_cost'
                )) {

                    $table->decimal(
                        'average_cost',
                        18,
                        2
                    )->default(0);

                }

                if (!Schema::hasColumn(
                    'items',
                    'last_purchase_price'
                )) {

                    $table->decimal(
                        'last_purchase_price',
                        18,
                        2
                    )->default(0);

                }
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'items',
            function (Blueprint $table) {

                if (Schema::hasColumn(
                    'items',
                    'average_cost'
                )) {

                    $table->dropColumn(
                        'average_cost'
                    );

                }

                if (Schema::hasColumn(
                    'items',
                    'last_purchase_price'
                )) {

                    $table->dropColumn(
                        'last_purchase_price'
                    );

                }
            }
        );
    }
};
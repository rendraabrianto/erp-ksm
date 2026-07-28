<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->foreignId('company_id')
                ->nullable()
                ->after('id')
                ->constrained();

            $table->foreignId('branch_id')
                ->nullable()
                ->after('company_id')
                ->constrained();

            $table->boolean('is_active')
                ->default(true)
                ->after('remember_token');

            $table->timestamp('last_login_at')
                ->nullable()
                ->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->dropForeign(['company_id']);
            $table->dropForeign(['branch_id']);

            $table->dropColumn([
                'company_id',
                'branch_id',
                'is_active',
                'last_login_at'
            ]);
        });
    }
};

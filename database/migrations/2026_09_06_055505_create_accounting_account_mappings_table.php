<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'accounting_account_mappings',
            function (Blueprint $table) {
                $table->id();

                $table
                    ->foreignId('company_id')
                    ->constrained('companies')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();

                $table
                    ->foreignId('grni_account_id')
                    ->constrained('accounts')
                    ->restrictOnDelete();

                $table
                    ->foreignId('ap_account_id')
                    ->constrained('accounts')
                    ->restrictOnDelete();

                $table
                    ->foreignId('ar_account_id')
                    ->constrained('accounts')
                    ->restrictOnDelete();

                $table->timestamps();

                $table->unique(
                    'company_id',
                    'accounting_account_mappings_company_unique'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'accounting_account_mappings'
        );
    }
};
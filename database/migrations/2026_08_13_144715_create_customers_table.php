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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code',30)->unique();
            $table->string('name');
            $table->string('phone',50)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->decimal(
                'credit_limit',
                18,
                2
            )->default(0);
            $table->integer(
                'credit_days'
            )->default(0);
            $table->boolean(
                'is_active'
            )->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};

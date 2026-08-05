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
        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->date('journal_date');
            $table->string('journal_no',50)
                ->unique();
            $table->string('reference_type',100)
                ->nullable();
            $table->unsignedBigInteger('reference_id')
                ->nullable();
            $table->string('description')
                ->nullable();
            $table->enum(
                'status',
                [
                    'DRAFT',
                    'POSTED',
                    'CANCELLED'
                ]
            )->default('POSTED');
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            $table->index('journal_date');
            $table->index('journal_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journals');
    }
};

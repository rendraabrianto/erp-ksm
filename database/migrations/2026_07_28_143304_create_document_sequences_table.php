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
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 50);
            $table->string('prefix', 20);
            $table->string('description')->nullable();
            $table->integer('current_number')
                ->default(0);
            $table->integer('padding')
                ->default(5);
            $table->boolean('is_active')
                ->default(true);
            $table->timestamps();
            $table->unique('document_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};

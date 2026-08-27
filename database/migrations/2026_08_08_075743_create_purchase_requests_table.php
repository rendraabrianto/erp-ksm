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
        Schema::create('purchase_requests', function (Blueprint $table) {

            $table->id();

            $table->string('pr_no',50)->unique();

            $table->date('pr_date');

            $table->foreignId('warehouse_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->text('remarks')->nullable();

            $table->enum('status',[
                'DRAFT',
                'APPROVED',
                'REJECTED',
                'CLOSED'
            ])->default('DRAFT');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('pr_no');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};

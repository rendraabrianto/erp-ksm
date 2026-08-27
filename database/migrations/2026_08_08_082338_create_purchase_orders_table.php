<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (
            Blueprint $table
        ) {

            $table->id();

            $table->string(
                'po_no',
                50
            )->unique();

            $table->foreignId(
                'purchase_request_id'
            )
            ->nullable()
            ->constrained()
            ->nullOnDelete();

            $table->date('po_date');

            $table->string(
                'supplier_name',
                150
            );

            $table->text('remarks')
                ->nullable();

            $table->enum(
                'status',
                [
                    'DRAFT',
                    'APPROVED',
                    'PARTIAL',
                    'COMPLETED',
                    'CANCELLED'
                ]
            )->default('DRAFT');

            $table->foreignId(
                'created_by'
            )
            ->nullable()
            ->constrained('users')
            ->nullOnDelete();

            $table->timestamps();

            $table->index('po_no');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'purchase_orders'
        );
    }
};
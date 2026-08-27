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
        Schema::create('sales_invoices', function (Blueprint $table) {

            $table->id();
            $table->string('invoice_no')->unique();
            $table->foreignId('customer_id');
            $table->foreignId('delivery_order_id');
            $table->date('invoice_date');
            $table->date('due_date');
            $table->decimal('subtotal',18,2)->default(0);
            $table->decimal('discount_amount',18,2)->default(0);
            $table->decimal('tax_amount',18,2)->default(0);
            $table->decimal('grand_total',18,2)->default(0);
            $table->enum(
                'status',
                [
                    'DRAFT',
                    'POSTED',
                    'PAID'
                ]
            )->default('POSTED');

            $table->text('remarks')
                ->nullable();
            $table->foreignId('created_by');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_invoices');
    }
};

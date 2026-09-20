<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Preflight - NULL Customer Company Ownership
        |--------------------------------------------------------------------------
        |
        | Customer company ownership was backfilled in the previous migration.
        | Never enforce NOT NULL while legacy rows still have no company.
        |
        */

        $nullCustomerCompanyCount =
            DB::table('customers')
                ->whereNull('company_id')
                ->count();

        if ($nullCustomerCompanyCount > 0) {
            throw new \RuntimeException(
                'Cannot enforce customers.company_id NOT NULL: '
                . "{$nullCustomerCompanyCount} row(s) have NULL company_id."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Preflight - Orphan Customer Company Ownership
        |--------------------------------------------------------------------------
        */

        $orphanCustomerCompanyCount =
            DB::table('customers as customer')
                ->leftJoin(
                    'companies as company',
                    'company.id',
                    '=',
                    'customer.company_id'
                )
                ->whereNull('company.id')
                ->count();

        if ($orphanCustomerCompanyCount > 0) {
            throw new \RuntimeException(
                'Cannot enforce customers.company_id NOT NULL: '
                . "{$orphanCustomerCompanyCount} row(s) reference an invalid company."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Preflight - Sales Order Customer Ownership
        |--------------------------------------------------------------------------
        |
        | Sales Order and its Customer must belong to the same company.
        |
        */

        $crossCompanySalesOrders =
            DB::table('sales_orders as sales_order')
                ->join(
                    'customers as customer',
                    'customer.id',
                    '=',
                    'sales_order.customer_id'
                )
                ->whereColumn(
                    'sales_order.company_id',
                    '<>',
                    'customer.company_id'
                )
                ->count();

        if ($crossCompanySalesOrders > 0) {
            throw new \RuntimeException(
                'Cannot enforce customer company ownership: '
                . "{$crossCompanySalesOrders} sales order(s) belong to a "
                . 'different company than their customer.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Preflight - Sales Invoice Customer Ownership
        |--------------------------------------------------------------------------
        |
        | Sales Invoice and its Customer must belong to the same company.
        |
        */

        $crossCompanySalesInvoices =
            DB::table('sales_invoices as sales_invoice')
                ->join(
                    'customers as customer',
                    'customer.id',
                    '=',
                    'sales_invoice.customer_id'
                )
                ->whereColumn(
                    'sales_invoice.company_id',
                    '<>',
                    'customer.company_id'
                )
                ->count();

        if ($crossCompanySalesInvoices > 0) {
            throw new \RuntimeException(
                'Cannot enforce customer company ownership: '
                . "{$crossCompanySalesInvoices} sales invoice(s) belong to a "
                . 'different company than their customer.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Preflight - Account Receivable Customer Ownership
        |--------------------------------------------------------------------------
        |
        | Account Receivable and its Customer must belong to the same company.
        |
        */

        $crossCompanyAccountReceivables =
            DB::table('account_receivables as receivable')
                ->join(
                    'customers as customer',
                    'customer.id',
                    '=',
                    'receivable.customer_id'
                )
                ->whereColumn(
                    'receivable.company_id',
                    '<>',
                    'customer.company_id'
                )
                ->count();

        if ($crossCompanyAccountReceivables > 0) {
            throw new \RuntimeException(
                'Cannot enforce customer company ownership: '
                . "{$crossCompanyAccountReceivables} account receivable(s) belong to a "
                . 'different company than their customer.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Preflight - Customer Receipt Customer Ownership
        |--------------------------------------------------------------------------
        |
        | Customer Receipt and its Customer must belong to the same company.
        |
        */

        $crossCompanyCustomerReceipts =
            DB::table('customer_receipts as receipt')
                ->join(
                    'customers as customer',
                    'customer.id',
                    '=',
                    'receipt.customer_id'
                )
                ->whereColumn(
                    'receipt.company_id',
                    '<>',
                    'customer.company_id'
                )
                ->count();

        if ($crossCompanyCustomerReceipts > 0) {
            throw new \RuntimeException(
                'Cannot enforce customer company ownership: '
                . "{$crossCompanyCustomerReceipts} customer receipt(s) belong to a "
                . 'different company than their customer.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Enforce NOT NULL
        |--------------------------------------------------------------------------
        */

        Schema::table('customers', function (Blueprint $table) {
            $table
                ->unsignedBigInteger('company_id')
                ->nullable(false)
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table
                ->unsignedBigInteger('company_id')
                ->nullable()
                ->change();
        });
    }
};
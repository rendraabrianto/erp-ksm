<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Transaction document number definitions.
     *
     * @return array<string, array{
     *     column: string,
     *     old_unique: string,
     *     new_unique: string
     * }>
     */
    private function documentNumberDefinitions(): array
    {
        return [
            'purchase_requests' => [
                'column' =>
                    'pr_no',

                'old_unique' =>
                    'purchase_requests_pr_no_unique',

                'new_unique' =>
                    'purchase_requests_company_pr_no_unique',
            ],

            'purchase_orders' => [
                'column' =>
                    'po_no',

                'old_unique' =>
                    'purchase_orders_po_no_unique',

                'new_unique' =>
                    'purchase_orders_company_po_no_unique',
            ],

            'goods_receipts' => [
                'column' =>
                    'gr_no',

                'old_unique' =>
                    'goods_receipts_gr_no_unique',

                'new_unique' =>
                    'goods_receipts_company_gr_no_unique',
            ],

            'purchase_invoices' => [
                'column' =>
                    'invoice_no',

                'old_unique' =>
                    'purchase_invoices_invoice_no_unique',

                'new_unique' =>
                    'purchase_invoices_company_invoice_no_unique',
            ],

            'payment_vouchers' => [
                'column' =>
                    'voucher_no',

                'old_unique' =>
                    'payment_vouchers_voucher_no_unique',

                'new_unique' =>
                    'payment_vouchers_company_voucher_no_unique',
            ],

            'sales_orders' => [
                'column' =>
                    'so_no',

                'old_unique' =>
                    'sales_orders_so_no_unique',

                'new_unique' =>
                    'sales_orders_company_so_no_unique',
            ],

            'delivery_orders' => [
                'column' =>
                    'do_no',

                'old_unique' =>
                    'delivery_orders_do_no_unique',

                'new_unique' =>
                    'delivery_orders_company_do_no_unique',
            ],

            'sales_invoices' => [
                'column' =>
                    'invoice_no',

                'old_unique' =>
                    'sales_invoices_invoice_no_unique',

                'new_unique' =>
                    'sales_invoices_company_invoice_no_unique',
            ],

            'customer_receipts' => [
                'column' =>
                    'receipt_no',

                'old_unique' =>
                    'customer_receipts_receipt_no_unique',

                'new_unique' =>
                    'customer_receipts_company_receipt_no_unique',
            ],

            'inventory_adjustments' => [
                'column' =>
                    'adjustment_no',

                'old_unique' =>
                    'inventory_adjustments_adjustment_no_unique',

                'new_unique' =>
                    'inventory_adjustments_company_adjustment_no_unique',
            ],

            'inventory_transfers' => [
                'column' =>
                    'transfer_no',

                'old_unique' =>
                    'inventory_transfers_transfer_no_unique',

                'new_unique' =>
                    'inventory_transfers_company_transfer_no_unique',
            ],

            'journals' => [
                'column' =>
                    'journal_no',

                'old_unique' =>
                    'journals_journal_no_unique',

                'new_unique' =>
                    'journals_company_journal_no_unique',
            ],
        ];
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (
            $this->documentNumberDefinitions()
            as $table => $definition
        ) {
            $column =
                $definition['column'];

            /*
            |--------------------------------------------------------------------------
            | Safety Check
            |--------------------------------------------------------------------------
            |
            | A company must never contain the same document number twice.
            |
            */

            $duplicate =
                DB::table($table)
                    ->select(
                        'company_id',
                        $column,
                        DB::raw('COUNT(*) AS total')
                    )
                    ->groupBy(
                        'company_id',
                        $column
                    )
                    ->havingRaw(
                        'COUNT(*) > 1'
                    )
                    ->first();

            if ($duplicate) {
                throw new RuntimeException(
                    sprintf(
                        'Cannot scope %s.%s by company: duplicate document number exists within company %s.',
                        $table,
                        $column,
                        $duplicate->company_id
                    )
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Replace Global Unique With Company-Scoped Unique
            |--------------------------------------------------------------------------
            */

            Schema::table(
                $table,
                function (Blueprint $blueprint) use ($definition, $column) {
                    $blueprint->dropUnique(
                        $definition['old_unique']
                    );

                    $blueprint->unique(
                        [
                            'company_id',
                            $column,
                        ],
                        $definition['new_unique']
                    );
                }
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Rollback Safety
        |--------------------------------------------------------------------------
        |
        | Restoring global uniqueness is impossible if two companies already use
        | the same document number. Validate every table before modifying indexes.
        |
        */

        foreach (
            $this->documentNumberDefinitions()
            as $table => $definition
        ) {
            $column =
                $definition['column'];

            $duplicateAcrossCompanies =
                DB::table($table)
                    ->select(
                        $column,
                        DB::raw('COUNT(DISTINCT company_id) AS company_count')
                    )
                    ->groupBy(
                        $column
                    )
                    ->havingRaw(
                        'COUNT(DISTINCT company_id) > 1'
                    )
                    ->first();

            if ($duplicateAcrossCompanies) {
                throw new RuntimeException(
                    sprintf(
                        'Cannot rollback company-scoped document number for %s.%s: document number %s exists across multiple companies.',
                        $table,
                        $column,
                        $duplicateAcrossCompanies->{$column}
                    )
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Restore Global Unique Indexes
        |--------------------------------------------------------------------------
        */

        foreach (
            array_reverse(
                $this->documentNumberDefinitions(),
                true
            )
            as $table => $definition
        ) {
            $column =
                $definition['column'];

            Schema::table(
                $table,
                function (Blueprint $blueprint) use ($definition, $column) {
                    $blueprint->dropUnique(
                        $definition['new_unique']
                    );

                    $blueprint->unique(
                        $column,
                        $definition['old_unique']
                    );
                }
            );
        }
    }
};
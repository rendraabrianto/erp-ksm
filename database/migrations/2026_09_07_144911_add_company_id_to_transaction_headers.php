<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    private array $tables = [
        'purchase_requests',
        'purchase_orders',
        'goods_receipts',
        'purchase_invoices',
        'payment_vouchers',
        'sales_orders',
        'delivery_orders',
        'sales_invoices',
        'customer_receipts',
        'account_payables',
        'account_receivables',
        'inventory_adjustments',
        'inventory_transfers',
        'journals',
    ];

    public function up(): void
    {
        /*
         * Step 1
         * Add nullable company_id first so existing data can be backfilled.
         */
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->index();
            });
        }

        /*
         * Step 2
         * Backfill records that have a direct warehouse ownership path.
         */
        DB::statement("
            UPDATE purchase_requests pr
            INNER JOIN warehouses w
                ON w.id = pr.warehouse_id
            SET pr.company_id = w.company_id
            WHERE pr.company_id IS NULL
        ");

        DB::statement("
            UPDATE inventory_adjustments ia
            INNER JOIN warehouses w
                ON w.id = ia.warehouse_id
            SET ia.company_id = w.company_id
            WHERE ia.company_id IS NULL
        ");

        DB::statement("
            UPDATE inventory_transfers it
            INNER JOIN warehouses w
                ON w.id = it.source_warehouse_id
            SET it.company_id = w.company_id
            WHERE it.company_id IS NULL
        ");

        /*
         * Step 3
         * Purchase document chain.
         */
        DB::statement("
            UPDATE purchase_orders po
            INNER JOIN purchase_requests pr
                ON pr.id = po.purchase_request_id
            SET po.company_id = pr.company_id
            WHERE po.company_id IS NULL
              AND pr.company_id IS NOT NULL
        ");

        DB::statement("
            UPDATE goods_receipts gr
            INNER JOIN purchase_orders po
                ON po.id = gr.purchase_order_id
            SET gr.company_id = po.company_id
            WHERE gr.company_id IS NULL
              AND po.company_id IS NOT NULL
        ");

        DB::statement("
            UPDATE purchase_invoices pi
            INNER JOIN goods_receipts gr
                ON gr.id = pi.goods_receipt_id
            SET pi.company_id = gr.company_id
            WHERE pi.company_id IS NULL
              AND gr.company_id IS NOT NULL
        ");

        DB::statement("
            UPDATE account_payables ap
            INNER JOIN purchase_invoices pi
                ON ap.reference_type = 'PURCHASE_INVOICE'
               AND ap.reference_id = pi.id
            SET ap.company_id = pi.company_id
            WHERE ap.company_id IS NULL
              AND pi.company_id IS NOT NULL
        ");

        DB::statement("
            UPDATE payment_vouchers pv
            INNER JOIN account_payables ap
                ON ap.id = pv.account_payable_id
            SET pv.company_id = ap.company_id
            WHERE pv.company_id IS NULL
              AND ap.company_id IS NOT NULL
        ");

        /*
         * Step 4
         * Sales document chain.
         *
         * sales_orders currently have no warehouse/company relation,
         * so legacy rows are initially resolved from their creator.
         */
        $this->backfillFromCreator('sales_orders');

        DB::statement("
            UPDATE delivery_orders dox
            INNER JOIN sales_orders sox
                ON sox.id = dox.sales_order_id
            SET dox.company_id = sox.company_id
            WHERE dox.company_id IS NULL
              AND sox.company_id IS NOT NULL
        ");

        DB::statement("
            UPDATE sales_invoices si
            INNER JOIN delivery_orders dox
                ON dox.id = si.delivery_order_id
            SET si.company_id = dox.company_id
            WHERE si.company_id IS NULL
              AND dox.company_id IS NOT NULL
        ");

        DB::statement("
            UPDATE account_receivables ar
            INNER JOIN sales_invoices si
                ON si.id = ar.sales_invoice_id
            SET ar.company_id = si.company_id
            WHERE ar.company_id IS NULL
              AND si.company_id IS NOT NULL
        ");

        DB::statement("
            UPDATE customer_receipts cr
            INNER JOIN account_receivables ar
                ON ar.id = cr.account_receivable_id
            SET cr.company_id = ar.company_id
            WHERE cr.company_id IS NULL
              AND ar.company_id IS NOT NULL
        ");

        /*
         * Step 5
         * Legacy fallbacks.
         *
         * Some old documents are intentionally/manual-created and do not
         * have a complete parent chain. Resolve those from created_by.
         */
        foreach ([
            'purchase_requests',
            'purchase_orders',
            'goods_receipts',
            'purchase_invoices',
            'payment_vouchers',
            'sales_orders',
            'delivery_orders',
            'sales_invoices',
            'customer_receipts',
            'inventory_adjustments',
            'inventory_transfers',
            'journals',
        ] as $tableName) {
            $this->backfillFromCreator($tableName);
        }

        /*
         * Step 6
         * AP / AR do not have created_by, so they must resolve through
         * their owning accounting document.
         */
        DB::statement("
            UPDATE account_payables ap
            INNER JOIN purchase_invoices pi
                ON ap.reference_type = 'PURCHASE_INVOICE'
               AND ap.reference_id = pi.id
            SET ap.company_id = pi.company_id
            WHERE ap.company_id IS NULL
              AND pi.company_id IS NOT NULL
        ");

        DB::statement("
            UPDATE account_receivables ar
            INNER JOIN sales_invoices si
                ON si.id = ar.sales_invoice_id
            SET ar.company_id = si.company_id
            WHERE ar.company_id IS NULL
              AND si.company_id IS NOT NULL
        ");

        /*
         * Step 7
         * Do not silently leave ambiguous transaction ownership.
         */
        foreach ($this->tables as $tableName) {
            $missing = DB::table($tableName)
                ->whereNull('company_id')
                ->count();

            if ($missing > 0) {
                throw new \RuntimeException(
                    "Cannot backfill company_id for {$missing} row(s) in {$tableName}."
                );
            }
        }

        /*
         * Step 8
         * Add FK only after successful backfill.
         *
         * We intentionally do NOT cascade company deletion into
         * transactional history.
         */
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('company_id')
                    ->references('id')
                    ->on('companies')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }
    }

    private function backfillFromCreator(string $tableName): void
    {
        DB::statement("
            UPDATE {$tableName} t
            INNER JOIN users u
                ON u.id = t.created_by
            SET t.company_id = u.company_id
            WHERE t.company_id IS NULL
              AND u.company_id IS NOT NULL
        ");
    }
};
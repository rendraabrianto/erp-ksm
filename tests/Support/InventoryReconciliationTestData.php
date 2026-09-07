<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InventoryReconciliationTestData
{
    public static function create(): array
    {


        /*
        |--------------------------------------------------------------------------
        | Document Sequence
        |--------------------------------------------------------------------------
        |
        | JournalPostingService membutuhkan sequence JV untuk menghasilkan
        | journal_no melalui DocumentSequenceService.
        |
        */

        DB::table('document_sequences')->insert([
            'document_type'  => 'JV',
            'prefix'         => 'JV',
            'description'    => 'Journal Voucher Test Sequence',
            'current_number' => 0,
            'padding'        => 5,
            'is_active'      => true,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Company
        |--------------------------------------------------------------------------
        */

        $companyId = DB::table('companies')->insertGetId([
            'code'       => 'TEST',
            'name'       => 'Test Company',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Branch
        |--------------------------------------------------------------------------
        */

        $branchId = DB::table('branches')->insertGetId([
            'company_id' => $companyId,
            'code'       => 'TEST-BR',
            'name'       => 'Test Branch',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | User
        |--------------------------------------------------------------------------
        */

        $userId = DB::table('users')->insertGetId([
            'company_id' => $companyId,
            'branch_id'  => $branchId,
            'name'       => 'Test User',
            'email'      => 'inventory-test@example.test',
            'password'   => Hash::make('password'),
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Warehouse
        |--------------------------------------------------------------------------
        */

        $warehouseId = DB::table('warehouses')->insertGetId([
            'company_id' => $companyId,
            'branch_id'  => $branchId,
            'code'       => 'WH-TEST',
            'name'       => 'Test Warehouse',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | UOM
        |--------------------------------------------------------------------------
        */

        $uomId = DB::table('uoms')->insertGetId([
            'code'       => 'BAG-T',
            'name'       => 'Bag Test',
            'symbol'     => 'BAG',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Account Groups
        |--------------------------------------------------------------------------
        */

        $assetGroupId = DB::table('account_groups')->insertGetId([
            'code'       => 'AST-T',
            'name'       => 'Asset Test',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $expenseGroupId = DB::table('account_groups')->insertGetId([
            'code'       => 'EXP-T',
            'name'       => 'Expense Test',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $liabilityGroupId = DB::table('account_groups')->insertGetId([
            'code'       => 'LIA-T',
            'name'       => 'Liability Test',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Accounts
        |--------------------------------------------------------------------------
        */

        $inventoryAccountId = DB::table('accounts')->insertGetId([
            'account_group_id' => $assetGroupId,
            'code'             => '1201-T',
            'name'             => 'Persediaan Test',
            'normal_balance'   => 'DEBIT',
            'is_header'        => false,
            'is_active'        => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $cogsAccountId = DB::table('accounts')->insertGetId([
            'account_group_id' => $expenseGroupId,
            'code'             => '5001-T',
            'name'             => 'HPP Test',
            'normal_balance'   => 'DEBIT',
            'is_header'        => false,
            'is_active'        => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $grniAccountId = DB::table('accounts')->insertGetId([
            'account_group_id' => $liabilityGroupId,
            'code'             => '2101',
            'name'             => 'GRNI Test',
            'normal_balance'   => 'CREDIT',
            'is_header'        => false,
            'is_active'        => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $apAccountId = DB::table('accounts')->insertGetId([
            'account_group_id' => $liabilityGroupId,
            'code'             => '2001-T',
            'name'             => 'Hutang Dagang Test',
            'normal_balance'   => 'CREDIT',
            'is_header'        => false,
            'is_active'        => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $arAccountId = DB::table('accounts')->insertGetId([
            'account_group_id' => $assetGroupId,
            'code'             => '1101-T',
            'name'             => 'Piutang Dagang Test',
            'normal_balance'   => 'DEBIT',
            'is_header'        => false,
            'is_active'        => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Accounting Control Account Mapping
        |--------------------------------------------------------------------------
        */

        DB::table('accounting_account_mappings')->insert([
            'company_id' =>
                $companyId,

            'grni_account_id' =>
                $grniAccountId,

            'ap_account_id' =>
                $apAccountId,

            'ar_account_id' =>
                $arAccountId,

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Item Category
        |--------------------------------------------------------------------------
        */

        $categoryId = DB::table('item_categories')->insertGetId([
            'code'                 => 'FEED-T',
            'name'                 => 'Feed Test',
            'description'          => 'Inventory reconciliation test category',
            'is_active'            => true,
            'inventory_account_id' => $inventoryAccountId,
            'cogs_account_id'      => $cogsAccountId,
            'sales_account_id'     => null,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Item
        |--------------------------------------------------------------------------
        */

        $itemId = DB::table('items')->insertGetId([
            'item_category_id'  => $categoryId,
            'uom_id'            => $uomId,
            'code'              => 'ITEM-TEST',
            'name'              => 'Inventory Test Item',
            'minimum_stock'     => 0,
            'maximum_stock'     => 0,
            'average_cost'      => 0,
            'last_purchase_price' => 0,
            'is_active'         => true,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Stock Ledger
        |--------------------------------------------------------------------------
        |
        | Opening:
        | 100 x 5,000 = 500,000
        |
        | Goods Receipt:
        | 100 x 7,000 = 700,000
        |
        | Total:
        | Qty   = 200
        | Value = 1,200,000
        | Avg   = 6,000
        |
        */

        DB::table('stock_ledgers')->insert([
            [
                'warehouse_id'    => $warehouseId,
                'item_id'         => $itemId,
                'transaction_date'=> '2026-08-01',
                'reference_type'  => 'OPENING',
                'reference_id'    => 1,
                'qty_in'          => 100,
                'qty_out'         => 0,
                'balance_qty'     => 100,
                'unit_cost'       => 5000,
                'total_cost'      => 500000,
                'remarks'         => 'Opening Test',
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'warehouse_id'    => $warehouseId,
                'item_id'         => $itemId,
                'transaction_date'=> '2026-08-02',
                'reference_type'  => 'GOODS_RECEIPT',
                'reference_id'    => 100,
                'qty_in'          => 100,
                'qty_out'         => 0,
                'balance_qty'     => 200,
                'unit_cost'       => 7000,
                'total_cost'      => 1200000,
                'remarks'         => 'Goods Receipt Test',
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Delivery Orders
        |--------------------------------------------------------------------------
        |
        | Moving average = 6,000
        |
        | DO 201 = 10 x 6,000 = 60,000 -> missing
        | DO 202 =  5 x 6,000 = 30,000 -> missing
        | DO 203 =  5 x 6,000 = 30,000 -> missing
        | DO 204 =  5 x 6,000 = 30,000 -> posted only 20,000
        | DO 205 =  2 x 6,000 = 12,000 -> posted only 8,000
        |
        */

        $deliveries = [
            [201, '2026-08-03', 10, 190, 1140000],
            [202, '2026-08-04', 5,  185, 1110000],
            [203, '2026-08-05', 5,  180, 1080000],
            [204, '2026-08-06', 5,  175, 1050000],
            [205, '2026-08-07', 2,  173, 1038000],
        ];

        foreach ($deliveries as [
            $referenceId,
            $date,
            $qty,
            $balance,
            $totalCost
        ]) {
            DB::table('stock_ledgers')->insert([
                'warehouse_id'     => $warehouseId,
                'item_id'          => $itemId,
                'transaction_date' => $date,
                'reference_type'   => 'DELIVERY_ORDER',
                'reference_id'     => $referenceId,
                'qty_in'           => 0,
                'qty_out'          => $qty,
                'balance_qty'      => $balance,
                'unit_cost'        => 6000,
                'total_cost'       => $totalCost,
                'remarks'          => 'Delivery Test',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Correct Journal for Goods Receipt
        |--------------------------------------------------------------------------
        */

        $grJournalId = self::journal(
            userId: $userId,
            date: '2026-08-02',
            number: 'TEST-JV-GR-100',
            referenceType: 'GOODS_RECEIPT',
            referenceId: 100,
            description: 'Correct GR Journal'
        );

        self::detail(
            $grJournalId,
            $inventoryAccountId,
            700000,
            0,
            'Inventory'
        );

        self::detail(
            $grJournalId,
            $grniAccountId,
            0,
            700000,
            'GRNI'
        );

        /*
        |--------------------------------------------------------------------------
        | Under-posted DO 204
        |--------------------------------------------------------------------------
        */

        $do204JournalId = self::journal(
            userId: $userId,
            date: '2026-08-06',
            number: 'TEST-JV-DO-204',
            referenceType: 'DELIVERY_ORDER',
            referenceId: 204,
            description: 'Under Posted DO 204'
        );

        self::detail(
            $do204JournalId,
            $cogsAccountId,
            20000,
            0,
            'HPP'
        );

        self::detail(
            $do204JournalId,
            $inventoryAccountId,
            0,
            20000,
            'Persediaan'
        );

        /*
        |--------------------------------------------------------------------------
        | Under-posted DO 205
        |--------------------------------------------------------------------------
        */

        $do205JournalId = self::journal(
            userId: $userId,
            date: '2026-08-07',
            number: 'TEST-JV-DO-205',
            referenceType: 'DELIVERY_ORDER',
            referenceId: 205,
            description: 'Under Posted DO 205'
        );

        self::detail(
            $do205JournalId,
            $cogsAccountId,
            8000,
            0,
            'HPP'
        );

        self::detail(
            $do205JournalId,
            $inventoryAccountId,
            0,
            8000,
            'Persediaan'
        );

        /*
        |--------------------------------------------------------------------------
        | Orphan GR Journal
        |--------------------------------------------------------------------------
        */

        $orphanGrId = self::journal(
            userId: $userId,
            date: '2026-08-08',
            number: 'TEST-JV-ORPHAN-GR',
            referenceType: 'GOODS_RECEIPT',
            referenceId: 9991,
            description: 'Orphan GR Journal'
        );

        self::detail(
            $orphanGrId,
            $inventoryAccountId,
            50000,
            0,
            'Inventory'
        );

        self::detail(
            $orphanGrId,
            $grniAccountId,
            0,
            50000,
            'GRNI'
        );

        /*
        |--------------------------------------------------------------------------
        | Two Orphan DO Journals
        |--------------------------------------------------------------------------
        */

        foreach ([9992, 9993] as $index => $referenceId) {

            $journalId = self::journal(
                userId: $userId,
                date: '2026-08-08',
                number: 'TEST-JV-ORPHAN-DO-' . ($index + 1),
                referenceType: 'DELIVERY_ORDER',
                referenceId: $referenceId,
                description: 'Orphan DO Journal'
            );

            self::detail(
                $journalId,
                $cogsAccountId,
                10000,
                0,
                'HPP'
            );

            self::detail(
                $journalId,
                $inventoryAccountId,
                0,
                10000,
                'Persediaan'
            );
        }

        return [
            'company_id'           => $companyId,
            'branch_id'            => $branchId,
            'user_id'              => $userId,
            'warehouse_id'         => $warehouseId,
            'item_id'              => $itemId,
            'inventory_account_id' => $inventoryAccountId,
            'cogs_account_id'      => $cogsAccountId,
            'grni_account_id'      => $grniAccountId,
            'ap_account_id'        => $apAccountId,
            'ar_account_id'        => $arAccountId,
        ];

    }

    private static function journal(
        int $userId,
        string $date,
        string $number,
        string $referenceType,
        int $referenceId,
        string $description
    ): int {
        return DB::table('journals')->insertGetId([
            'journal_date'      => $date,
            'journal_no'        => $number,
            'reference_type'    => $referenceType,
            'reference_id'      => $referenceId,
            'journal_purpose'   => 'NORMAL',
            'source_journal_id' => null,
            'reconciliation_key'=> null,
            'description'       => $description,
            'status'            => 'POSTED',
            'created_by'        => $userId,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    private static function detail(
        int $journalId,
        int $accountId,
        float $debit,
        float $credit,
        string $description
    ): void {
        DB::table('journal_details')->insert([
            'journal_id' => $journalId,
            'account_id' => $accountId,
            'quantity'   => 0,
            'unit_price' => 0,
            'debit'      => $debit,
            'credit'     => $credit,
            'description'=> $description,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

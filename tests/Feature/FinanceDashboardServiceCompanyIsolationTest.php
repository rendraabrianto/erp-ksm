<?php

namespace Tests\Feature;

use App\DTO\FinanceDashboardFilterDTO;
use App\Services\FinanceDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class FinanceDashboardServiceCompanyIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_is_not_affected_by_another_company(): void
    {
        /*
         * --------------------------------------------------------------
         * Company A
         * --------------------------------------------------------------
         *
         * Gunakan fixture inventory utama yang sudah stabil.
         */
        $fixtureA =
            InventoryReconciliationTestData::create();

        $companyAId =
            $fixtureA['company_id'];

        /*
         * Dashboard periode mencakup historical fixture Agustus.
         */
        $dto =
            new FinanceDashboardFilterDTO(
                dateFrom: '2026-08-01',
                dateTo: '2026-09-30',
            );

        /** @var FinanceDashboardService $service */
        $service =
            app(FinanceDashboardService::class);

        /*
         * --------------------------------------------------------------
         * Baseline Company A
         * --------------------------------------------------------------
         */
        $before =
            $service->getDashboard(
                $companyAId,
                $dto
            );

        /*
         * Pastikan baseline memang membaca inventory Company A.
         */
        $this->assertNotEmpty(
            $before['inventory_items']
        );

        $this->assertTrue(
            collect($before['inventory_items'])
                ->contains(
                    fn ($row) =>
                        (int) $row['item_id']
                        ===
                        (int) $fixtureA['item_id']
                )
        );

        /*
         * --------------------------------------------------------------
         * Inject Company B
         * --------------------------------------------------------------
         *
         * Company B sengaja diberi inventory sangat besar.
         * Jika company isolation bocor, inventory_value Company A
         * akan berubah secara drastis.
         */
        $companyBId =
            DB::table('companies')
                ->insertGetId([
                    'code' => 'FD-SVC-B',
                    'name' => 'Finance Dashboard Service Company B',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        $branchBId =
            DB::table('branches')
                ->insertGetId([
                    'company_id' => $companyBId,
                    'code' => 'FD-SVC-B-BR',
                    'name' => 'Finance Dashboard Service Branch B',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        $warehouseBId =
            DB::table('warehouses')
                ->insertGetId([
                    'company_id' => $companyBId,
                    'branch_id' => $branchBId,
                    'code' => 'FD-SVC-B-WH',
                    'name' => 'Finance Dashboard Service Warehouse B',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        $uomBId =
            DB::table('uoms')
                ->insertGetId([
                    'code' => 'FD-SVC-UOM',
                    'name' => 'Finance Dashboard Service UOM',
                    'symbol' => 'UNIT',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        $assetGroupBId =
            DB::table('account_groups')
                ->insertGetId([
                    'company_id' => $companyBId,
                    'code' => 'AST-B',
                    'name' => 'Finance Dashboard Asset B',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        $inventoryAccountBId =
            DB::table('accounts')
                ->insertGetId([
                    'company_id' => $companyBId,
                    'account_group_id' => $assetGroupBId,
                    'code' => '1201-B',
                    'name' => 'Inventory Company B',
                    'normal_balance' => 'DEBIT',
                    'is_header' => false,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        $categoryBId =
            DB::table('item_categories')
                ->insertGetId([
                    'company_id' => $companyBId,
                    'code' => 'FD-SVC-B-CAT',
                    'name' => 'Finance Dashboard Category B',
                    'description' => 'Company isolation test',
                    'is_active' => true,
                    'inventory_account_id' => $inventoryAccountBId,
                    'cogs_account_id' => null,
                    'sales_account_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        $itemBId =
            DB::table('items')
                ->insertGetId([
                    'company_id' => $companyBId,
                    'item_category_id' => $categoryBId,
                    'uom_id' => $uomBId,
                    'code' => 'FD-SVC-B-ITEM',
                    'name' => 'Finance Dashboard Item B',
                    'minimum_stock' => 0,
                    'maximum_stock' => 0,
                    'average_cost' => 0,
                    'last_purchase_price' => 0,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        /*
         * Company B:
         *
         * 10,000 unit x 999,999
         *
         * Nilainya sengaja sangat besar supaya kebocoran tenant
         * langsung terlihat.
         */
        DB::table('stock_ledgers')
            ->insert([
                'company_id' => $companyBId,
                'warehouse_id' => $warehouseBId,
                'item_id' => $itemBId,
                'transaction_date' => '2026-09-20',
                'reference_type' => 'OPENING',
                'reference_id' => 990001,
                'qty_in' => 10000,
                'qty_out' => 0,
                'balance_qty' => 10000,
                'unit_cost' => 999999,
                'total_cost' => 9999990000,
                'remarks' => 'Company B isolation injection',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        /*
         * --------------------------------------------------------------
         * Company A after Company B injection
         * --------------------------------------------------------------
         */
        $after =
            $service->getDashboard(
                $companyAId,
                $dto
            );

        /*
         * --------------------------------------------------------------
         * KPI Company A harus identik
         * --------------------------------------------------------------
         */
        $this->assertEquals(
            $before['cash_position'],
            $after['cash_position']
        );

        $this->assertEquals(
            $before['inventory_value'],
            $after['inventory_value']
        );

        $this->assertEquals(
            $before['outstanding_ar'],
            $after['outstanding_ar']
        );

        $this->assertEquals(
            $before['overdue_ar'],
            $after['overdue_ar']
        );

        $this->assertEquals(
            $before['outstanding_ap'],
            $after['outstanding_ap']
        );

        $this->assertEquals(
            $before['overdue_ap'],
            $after['overdue_ap']
        );

        $this->assertEquals(
            $before['sales'],
            $after['sales']
        );

        $this->assertEquals(
            $before['cogs'],
            $after['cogs']
        );

        $this->assertEquals(
            $before['gross_profit'],
            $after['gross_profit']
        );

        $this->assertEquals(
            $before['operating_expense'],
            $after['operating_expense']
        );

        $this->assertEquals(
            $before['net_profit'],
            $after['net_profit']
        );

        /*
         * --------------------------------------------------------------
         * Detail inventory Company B tidak boleh muncul di Company A
         * --------------------------------------------------------------
         */
        $this->assertFalse(
            collect($after['inventory_items'])
                ->contains(
                    fn ($row) =>
                        (int) $row['item_id']
                        ===
                        $itemBId
                )
        );

        /*
         * Company A sendiri tetap harus ada.
         */
        $this->assertTrue(
            collect($after['inventory_items'])
                ->contains(
                    fn ($row) =>
                        (int) $row['item_id']
                        ===
                        (int) $fixtureA['item_id']
                )
        );

        /*
         * Low-stock Company B juga tidak boleh bocor.
         */
        $this->assertFalse(
            collect($after['low_stock_items'])
                ->contains(
                    fn ($row) =>
                        (int) $row['item_id']
                        ===
                        $itemBId
                )
        );
    }
}
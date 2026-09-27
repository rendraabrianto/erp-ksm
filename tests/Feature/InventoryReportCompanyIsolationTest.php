<?php

namespace Tests\Feature;

use App\DTO\CurrentStockFilterDTO;
use App\DTO\InventoryValuationFilterDTO;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StockLedger;
use App\Models\Warehouse;
use App\Services\CurrentStockService;
use App\Services\InventoryValuationReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class InventoryReportCompanyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    protected function setUp(): void
    {
        parent::setUp();

        $this->data =
            InventoryReconciliationTestData::create();
    }

    public function test_current_stock_only_returns_ledgers_from_requested_company():
        void
    {
        $companyBData =
            $this->createCompanyBStock();

        $result =
            app(
                CurrentStockService::class
            )->report(
                $this->data['company_id'],
                new CurrentStockFilterDTO(
                    warehouseId: null,
                    itemId: null,
                    asOfDate: null,
                )
            );

        $this->assertNotEmpty(
            $result['rows']
        );

        $warehouseIds =
            collect($result['rows'])
                ->pluck('warehouse_id');

        $itemIds =
            collect($result['rows'])
                ->pluck('item_id');

        $this->assertFalse(
            $warehouseIds->contains(
                $companyBData['warehouse_id']
            )
        );

        $this->assertFalse(
            $itemIds->contains(
                $companyBData['item_id']
            )
        );
    }

    public function test_current_stock_cannot_read_other_company_warehouse_filter():
        void
    {
        $companyBData =
            $this->createCompanyBStock();

        $result =
            app(
                CurrentStockService::class
            )->report(
                $this->data['company_id'],
                new CurrentStockFilterDTO(
                    warehouseId:
                        $companyBData['warehouse_id'],

                    itemId: null,
                    asOfDate: null,
                )
            );

        $this->assertCount(
            0,
            $result['rows']
        );

        $this->assertSame(
            0,
            $result['total_items']
        );

        $this->assertEquals(
            0.0,
            $result['total_inventory_value']
        );
    }

    public function test_current_stock_cannot_read_other_company_item_filter():
        void
    {
        $companyBData =
            $this->createCompanyBStock();

        $result =
            app(
                CurrentStockService::class
            )->report(
                $this->data['company_id'],
                new CurrentStockFilterDTO(
                    warehouseId: null,

                    itemId:
                        $companyBData['item_id'],

                    asOfDate: null,
                )
            );

        $this->assertCount(
            0,
            $result['rows']
        );

        $this->assertSame(
            0,
            $result['total_items']
        );
    }

    public function test_inventory_valuation_only_returns_requested_company():
        void
    {
        $companyBData =
            $this->createCompanyBStock();

        $result =
            app(
                InventoryValuationReportService::class
            )->report(
                $this->data['company_id'],
                new InventoryValuationFilterDTO(
                    warehouseId: null,
                    itemId: null,
                    asOfDate: null,
                )
            );

        $warehouseIds =
            collect($result['rows'])
                ->pluck('warehouse_id');

        $itemIds =
            collect($result['rows'])
                ->pluck('item_id');

        $this->assertFalse(
            $warehouseIds->contains(
                $companyBData['warehouse_id']
            )
        );

        $this->assertFalse(
            $itemIds->contains(
                $companyBData['item_id']
            )
        );
    }

    public function test_inventory_valuation_cannot_read_other_company_filters():
        void
    {
        $companyBData =
            $this->createCompanyBStock();

        $result =
            app(
                InventoryValuationReportService::class
            )->report(
                $this->data['company_id'],
                new InventoryValuationFilterDTO(
                    warehouseId:
                        $companyBData['warehouse_id'],

                    itemId:
                        $companyBData['item_id'],

                    asOfDate: null,
                )
            );

        $this->assertCount(
            0,
            $result['rows']
        );

        $this->assertSame(
            0,
            $result['total_items']
        );

        $this->assertEquals(
            0.0,
            $result['total_inventory_value']
        );
    }

    private function createCompanyBStock():
        array
    {
        $company =
            Company::create([
                'code' =>
                    'COMP-B-REPORT',

                'name' =>
                    'Company B Report',

                'is_active' =>
                    true,
            ]);

        $branch =
            Branch::create([
                'company_id' =>
                    $company->id,

                'code' =>
                    'BR-B-REPORT',

                'name' =>
                    'Branch B Report',

                'is_active' =>
                    true,
            ]);

        $warehouse =
            Warehouse::create([
                'company_id' =>
                    $company->id,

                'branch_id' =>
                    $branch->id,

                'code' =>
                    'WH-REPORT',

                'name' =>
                    'Warehouse Company B',

                'is_active' =>
                    true,
            ]);

        $categoryA =
            ItemCategory::query()
                ->where(
                    'company_id',
                    $this->data['company_id']
                )
                ->firstOrFail();

        $category =
            ItemCategory::create([
                'company_id' =>
                    $company->id,

                'code' =>
                    'CAT-B-REPORT',

                'name' =>
                    'Category Company B',

                'inventory_account_id' =>
                    null,

                'cogs_account_id' =>
                    null,

                'sales_account_id' =>
                    null,

                'adjustment_gain_account_id' =>
                    null,

                'adjustment_loss_account_id' =>
                    null,
            ]);

        $sourceItem =
            Item::query()
                ->where(
                    'company_id',
                    $this->data['company_id']
                )
                ->firstOrFail();

        $item =
            Item::create([
                'company_id' =>
                    $company->id,

                'item_category_id' =>
                    $category->id,

                'uom_id' =>
                    $sourceItem->uom_id,

                'code' =>
                    'ITEM-B-REPORT',

                'name' =>
                    'Item Company B',

                'minimum_stock' =>
                    0,

                'maximum_stock' =>
                    1000,

                'average_cost' =>
                    9000,

                'is_active' =>
                    true,
            ]);

        StockLedger::create([
            'company_id' =>
                $company->id,

            'warehouse_id' =>
                $warehouse->id,

            'item_id' =>
                $item->id,

            'transaction_date' =>
                '2026-08-01',

            'reference_type' =>
                'OPENING',

            'reference_id' =>
                900001,

            'qty_in' =>
                50,

            'qty_out' =>
                0,

            'balance_qty' =>
                50,

            'unit_cost' =>
                9000,

            'total_cost' =>
                450000,
        ]);

        return [
            'company_id' =>
                $company->id,

            'warehouse_id' =>
                $warehouse->id,

            'item_id' =>
                $item->id,
        ];
    }
}
<?php

namespace Tests\Feature;

use App\DTO\CurrentStockFilterDTO;
use App\Services\CurrentStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class CurrentStockServiceTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    protected function setUp(): void
    {
        parent::setUp();

        $this->data =
            InventoryReconciliationTestData::create();
    }

    public function test_existing_get_method_still_returns_current_quantity():
        void
    {
        $qty =
            app(
                CurrentStockService::class
            )->get(
                $this->data['warehouse_id'],
                $this->data['item_id']
            );

        $this->assertEquals(
            173.0,
            $qty
        );
    }

    public function test_it_returns_current_stock_from_latest_ledger():
        void
    {
        $result =
            app(
                CurrentStockService::class
            )->report(
                new CurrentStockFilterDTO(
                    warehouseId:
                        $this->data['warehouse_id'],

                    itemId:
                        $this->data['item_id'],
                )
            );

        $this->assertSame(
            1,
            $result['total_items']
        );

        $row =
            $result['rows'][0];

        $this->assertEquals(
            173.0,
            $row['qty_on_hand']
        );

        $this->assertEquals(
            1038000.0,
            $row['inventory_value']
        );

        $this->assertEquals(
            6000.0,
            $row['average_cost']
        );
    }

    public function test_it_can_calculate_stock_as_of_date():
        void
    {
        $result =
            app(
                CurrentStockService::class
            )->report(
                new CurrentStockFilterDTO(
                    warehouseId:
                        $this->data['warehouse_id'],

                    itemId:
                        $this->data['item_id'],

                    asOfDate:
                        '2026-08-04',
                )
            );

        $row =
            $result['rows'][0];

        $this->assertEquals(
            185.0,
            $row['qty_on_hand']
        );

        $this->assertEquals(
            1110000.0,
            $row['inventory_value']
        );

        $this->assertEquals(
            6000.0,
            $row['average_cost']
        );
    }

    public function test_it_returns_summary_values():
        void
    {
        $result =
            app(
                CurrentStockService::class
            )->report(
                new CurrentStockFilterDTO(
                    warehouseId:
                        $this->data['warehouse_id'],
                )
            );

        $this->assertSame(
            1,
            $result['total_items']
        );

        $this->assertEquals(
            1038000.0,
            $result['total_inventory_value']
        );
    }

    public function test_it_detects_below_minimum_stock():
        void
    {
        $result =
            app(
                CurrentStockService::class
            )->report(
                new CurrentStockFilterDTO(
                    warehouseId:
                        $this->data['warehouse_id'],
                )
            );

        $row =
            $result['rows'][0];

        $this->assertFalse(
            $row['is_below_minimum']
        );

        $this->assertSame(
            0,
            $result['below_minimum_count']
        );
    }
}
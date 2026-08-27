<?php

namespace Tests\Feature;

use App\DTO\CurrentStockFilterDTO;
use App\DTO\InventoryValuationFilterDTO;
use App\Services\CurrentStockService;
// use App\Services\InventoryValuationService;
use App\Services\InventoryValuationReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class InventoryValuationServiceTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    protected function setUp(): void
    {
        parent::setUp();

        $this->data =
            InventoryReconciliationTestData::create();
    }

    public function test_it_returns_current_inventory_valuation():
        void
    {
        $result =
            app(
                InventoryValuationReportService::class
            )->report(
                new InventoryValuationFilterDTO(
                    warehouseId:
                        $this->data[
                            'warehouse_id'
                        ],

                    itemId:
                        $this->data[
                            'item_id'
                        ],
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
            6000.0,
            $row['average_cost']
        );

        $this->assertEquals(
            1038000.0,
            $row['inventory_value']
        );
    }

    public function test_it_returns_historical_inventory_valuation():
        void
    {
        $result =
            app(
                InventoryValuationReportService::class
            )->report(
                new InventoryValuationFilterDTO(
                    warehouseId:
                        $this->data[
                            'warehouse_id'
                        ],

                    itemId:
                        $this->data[
                            'item_id'
                        ],

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
            6000.0,
            $row['average_cost']
        );

        $this->assertEquals(
            1110000.0,
            $row['inventory_value']
        );
    }

    public function test_summary_totals_are_correct():
        void
    {
        $result =
            app(
                InventoryValuationReportService::class
            )->report(
                new InventoryValuationFilterDTO(
                    warehouseId:
                        $this->data[
                            'warehouse_id'
                        ],
                )
            );

        $this->assertEquals(
            173.0,
            $result['total_qty']
        );

        $this->assertEquals(
            1038000.0,
            $result[
                'total_inventory_value'
            ]
        );
    }

    public function test_valuation_equals_current_stock():
        void
    {
        $valuation =
            app(
                InventoryValuationReportService::class
            )->report(
                new InventoryValuationFilterDTO(
                    warehouseId:
                        $this->data[
                            'warehouse_id'
                        ],

                    itemId:
                        $this->data[
                            'item_id'
                        ],
                )
            );

        $currentStock =
            app(
                CurrentStockService::class
            )->report(
                new CurrentStockFilterDTO(
                    warehouseId:
                        $this->data[
                            'warehouse_id'
                        ],

                    itemId:
                        $this->data[
                            'item_id'
                        ],
                )
            );

        $valuationRow =
            $valuation['rows'][0];

        $stockRow =
            $currentStock['rows'][0];

        $this->assertEquals(
            $stockRow['qty_on_hand'],
            $valuationRow['qty_on_hand']
        );

        $this->assertEquals(
            $stockRow['average_cost'],
            $valuationRow['average_cost']
        );

        $this->assertEquals(
            $stockRow[
                'inventory_value'
            ],
            $valuationRow[
                'inventory_value'
            ]
        );
    }

    public function test_historical_valuation_equals_historical_current_stock():
        void
    {
        $valuation =
            app(
                InventoryValuationReportService::class
            )->report(
                new InventoryValuationFilterDTO(
                    warehouseId:
                        $this->data[
                            'warehouse_id'
                        ],

                    itemId:
                        $this->data[
                            'item_id'
                        ],

                    asOfDate:
                        '2026-08-04',
                )
            );

        $currentStock =
            app(
                CurrentStockService::class
            )->report(
                new CurrentStockFilterDTO(
                    warehouseId:
                        $this->data[
                            'warehouse_id'
                        ],

                    itemId:
                        $this->data[
                            'item_id'
                        ],

                    asOfDate:
                        '2026-08-04',
                )
            );

        $valuationRow =
            $valuation['rows'][0];

        $stockRow =
            $currentStock['rows'][0];

        $this->assertEquals(
            $stockRow[
                'inventory_value'
            ],
            $valuationRow[
                'inventory_value'
            ]
        );
    }
}
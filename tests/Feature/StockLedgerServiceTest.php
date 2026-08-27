<?php

namespace Tests\Feature;

use App\Services\CurrentStockService;
use App\Services\StockLedgerService;
use App\DTO\CurrentStockFilterDTO;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class StockLedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    protected function setUp(): void
    {
        parent::setUp();

        $this->data =
            InventoryReconciliationTestData::create();
    }

    private function ledger()
    {
        return app(
            StockLedgerService::class
        )->getLedger(
            warehouseId:
                $this->data['warehouse_id'],

            itemId:
                $this->data['item_id'],

            dateFrom:
                '2026-08-01',

            dateTo:
                '2026-08-31',
        );
    }

    public function test_it_returns_stock_ledger_rows():
        void
    {
        $rows =
            $this->ledger();

        $this->assertNotEmpty(
            $rows
        );

        $this->assertGreaterThan(
            1,
            $rows->count()
        );
    }

    public function test_last_row_contains_correct_calculated_balance():
        void
    {
        $last =
            $this->ledger()
                ->last();

        $this->assertEquals(
            173.0,
            $last[
                'calculated_balance_qty'
            ]
        );

        $this->assertEquals(
            6000.0,
            $last[
                'calculated_average_cost'
            ]
        );

        $this->assertEquals(
            1038000.0,
            $last[
                'calculated_inventory_value'
            ]
        );
    }

    public function test_stock_ledger_final_equals_current_stock():
        void
    {
        $last =
            $this->ledger()
                ->last();

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

        $stock =
            $currentStock['rows'][0];

        $this->assertEquals(
            $stock['qty_on_hand'],
            $last[
                'calculated_balance_qty'
            ]
        );

        $this->assertEquals(
            $stock['average_cost'],
            $last[
                'calculated_average_cost'
            ]
        );

        $this->assertEquals(
            $stock['inventory_value'],
            $last[
                'calculated_inventory_value'
            ]
        );
    }

    public function test_outbound_uses_average_cost_before_transaction():
        void
    {
        $rows =
            $this->ledger();

        $outbound =
            $rows
                ->first(
                    function ($row) {

                        return
                            (float)
                            $row['ledger']
                                ->qty_out
                            >
                            0;
                    }
                );

        $this->assertNotNull(
            $outbound
        );

        $qtyOut =
            (float)
            $outbound['ledger']
                ->qty_out;

        $expectedOutboundCost =
            round(
                $qtyOut
                *
                $outbound[
                    'average_cost_before'
                ],
                2
            );

        $this->assertEquals(
            $expectedOutboundCost,
            $outbound[
                'calculated_outbound_cost'
            ]
        );
    }

    public function test_date_from_does_not_destroy_opening_balance():
        void
    {
        $service =
            app(
                StockLedgerService::class
            );

        $rows =
            $service->getLedger(
                warehouseId:
                    $this->data[
                        'warehouse_id'
                    ],

                itemId:
                    $this->data[
                        'item_id'
                    ],

                dateFrom:
                    '2026-08-03',

                dateTo:
                    '2026-08-31',
            );

        $this->assertNotEmpty(
            $rows
        );

        $first =
            $rows->first();

        $this->assertGreaterThan(
            0,
            $first[
                'average_cost_before'
            ]
        );
    }

    public function test_date_to_returns_correct_as_of_position():
        void
    {
        $service =
            app(
                StockLedgerService::class
            );

        $rows =
            $service->getLedger(
                warehouseId:
                    $this->data[
                        'warehouse_id'
                    ],

                itemId:
                    $this->data[
                        'item_id'
                    ],

                dateFrom:
                    null,

                dateTo:
                    '2026-08-04',
            );

        $last =
            $rows->last();

        $this->assertNotNull(
            $last
        );

        $this->assertEquals(
            185.0,
            $last[
                'calculated_balance_qty'
            ]
        );

        $this->assertEquals(
            6000.0,
            $last[
                'calculated_average_cost'
            ]
        );

        $this->assertEquals(
            1110000.0,
            $last[
                'calculated_inventory_value'
            ]
        );
    }
}
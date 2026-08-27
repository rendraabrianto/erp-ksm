<?php

namespace Tests\Feature;

use App\DTO\InventoryAdjustmentCreateDTO;
use App\Models\Account;
use App\Models\DocumentSequence;
use App\Models\InventoryAdjustment;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Journal;
use App\Models\StockLedger;
use App\Services\CurrentStockService;
use App\Services\InventoryAdjustmentService;
use App\Services\InventoryValuationReportService;
use App\DTO\CurrentStockFilterDTO;
use App\DTO\InventoryValuationFilterDTO;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class InventoryAdjustmentClosedLoopTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    protected function setUp(): void
    {
        parent::setUp();

        $this->data =
            InventoryReconciliationTestData::create();

        $existingAccount =
            Account::query()
                ->firstOrFail();

        $gainAccount =
            Account::firstOrCreate(
                ['code' => '4901'],
                [
                    'account_group_id' =>
                        $existingAccount->account_group_id,

                    'name' =>
                        'Pendapatan Selisih Persediaan',

                    'normal_balance' =>
                        'CREDIT',

                    'is_header' =>
                        false,

                    'is_active' =>
                        true,
                ]
            );

        $lossAccount =
            Account::firstOrCreate(
                ['code' => '6901'],
                [
                    'account_group_id' =>
                        $existingAccount->account_group_id,

                    'name' =>
                        'Beban Selisih Persediaan',

                    'normal_balance' =>
                        'DEBIT',

                    'is_header' =>
                        false,

                    'is_active' =>
                        true,
                ]
            );

        $item =
            Item::findOrFail(
                $this->data['item_id']
            );

        ItemCategory::where(
            'id',
            $item->item_category_id
        )->update([
            'adjustment_gain_account_id' =>
                $gainAccount->id,

            'adjustment_loss_account_id' =>
                $lossAccount->id,
        ]);

        DocumentSequence::firstOrCreate(
            ['document_type' => 'ADJ'],
            [
                'prefix' =>
                    'ADJ',

                'description' =>
                    'Inventory Adjustment',

                'current_number' =>
                    0,

                'padding' =>
                    5,

                'is_active' =>
                    true,
            ]
        );
    }

    private function createDraft(
        float $physicalQty
    ): InventoryAdjustment {

        return app(
            InventoryAdjustmentService::class
        )->create(
            new InventoryAdjustmentCreateDTO(
                warehouseId:
                    $this->data['warehouse_id'],

                adjustmentDate:
                    '2026-08-23',

                reason:
                    'STOCK_OPNAME',

                remarks:
                    'Closed loop test',

                createdBy:
                    $this->data['user_id'],

                details: [
                    [
                        'item_id' =>
                            $this->data['item_id'],

                        'physical_qty' =>
                            $physicalQty,

                        'remarks' =>
                            'Closed loop adjustment',
                    ],
                ],
            )
        );
    }

    public function test_posted_adjustment_updates_current_stock():
        void
    {
        $adjustment =
            $this->createDraft(
                170.0
            );

        app(
            InventoryAdjustmentService::class
        )->post(
            $adjustment->id,
            $this->data['user_id']
        );

        $currentStock =
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

        $row =
            $currentStock['rows'][0];

        $this->assertEquals(
            170.0,
            $row['qty_on_hand']
        );

        $this->assertEquals(
            6000.0,
            $row['average_cost']
        );

        $this->assertEquals(
            1020000.0,
            $row['inventory_value']
        );
    }

    public function test_posted_adjustment_updates_inventory_valuation():
        void
    {
        $adjustment =
            $this->createDraft(
                170.0
            );

        app(
            InventoryAdjustmentService::class
        )->post(
            $adjustment->id,
            $this->data['user_id']
        );

        $valuation =
            app(
                InventoryValuationReportService::class
            )->report(
                new InventoryValuationFilterDTO(
                    warehouseId:
                        $this->data['warehouse_id'],

                    itemId:
                        $this->data['item_id'],
                )
            );

        $row =
            $valuation['rows'][0];

        $this->assertEquals(
            170.0,
            $row['qty_on_hand']
        );

        $this->assertEquals(
            6000.0,
            $row['average_cost']
        );

        $this->assertEquals(
            1020000.0,
            $row['inventory_value']
        );
    }

    public function test_adjustment_stock_ledger_closing_matches_current_stock():
        void
    {
        $adjustment =
            $this->createDraft(
                170.0
            );

        app(
            InventoryAdjustmentService::class
        )->post(
            $adjustment->id,
            $this->data['user_id']
        );

        $ledger =
            StockLedger::query()
                ->where(
                    'reference_type',
                    'INVENTORY_ADJUSTMENT'
                )
                ->where(
                    'reference_id',
                    $adjustment->id
                )
                ->firstOrFail();

        $currentStock =
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

        $row =
            $currentStock['rows'][0];

        $this->assertEquals(
            (float)
            $ledger->balance_qty,

            $row['qty_on_hand']
        );
    }

    public function test_adjustment_journal_equals_inventory_value_change():
        void
    {
        $before =
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

        $beforeValue =
            (float)
            $before['rows'][0][
                'inventory_value'
            ];

        $adjustment =
            $this->createDraft(
                170.0
            );

        app(
            InventoryAdjustmentService::class
        )->post(
            $adjustment->id,
            $this->data['user_id']
        );

        $after =
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

        $afterValue =
            (float)
            $after['rows'][0][
                'inventory_value'
            ];

        $inventoryDecrease =
            round(
                $beforeValue
                -
                $afterValue,
                2
            );

        $journal =
            Journal::query()
                ->where(
                    'reference_type',
                    'INVENTORY_ADJUSTMENT'
                )
                ->where(
                    'reference_id',
                    $adjustment->id
                )
                ->with('details')
                ->firstOrFail();

        $inventoryCredit =
            (float)
            $journal->details
                ->where(
                    'account_id',
                    $this->data[
                        'inventory_account_id'
                    ]
                )
                ->sum('credit');

        $this->assertEquals(
            $inventoryDecrease,
            $inventoryCredit
        );

        $this->assertEquals(
            (float)
            $journal->details
                ->sum('debit'),

            (float)
            $journal->details
                ->sum('credit')
        );
    }

    public function test_adjustment_is_traceable_from_document_to_ledger_and_journal():
        void
    {
        $adjustment =
            $this->createDraft(
                170.0
            );

        app(
            InventoryAdjustmentService::class
        )->post(
            $adjustment->id,
            $this->data['user_id']
        );

        $this->assertDatabaseHas(
            'inventory_adjustments',
            [
                'id' =>
                    $adjustment->id,

                'status' =>
                    'POSTED',
            ]
        );

        $this->assertDatabaseHas(
            'stock_ledgers',
            [
                'reference_type' =>
                    'INVENTORY_ADJUSTMENT',

                'reference_id' =>
                    $adjustment->id,
            ]
        );

        $this->assertDatabaseHas(
            'journals',
            [
                'reference_type' =>
                    'INVENTORY_ADJUSTMENT',

                'reference_id' =>
                    $adjustment->id,
            ]
        );
    }

    public function test_second_post_does_not_change_closed_loop_position():
        void
    {
        $service =
            app(
                InventoryAdjustmentService::class
            );

        $adjustment =
            $this->createDraft(
                170.0
            );

        $service->post(
            $adjustment->id,
            $this->data['user_id']
        );

        $before =
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

        $ledgerCountBefore =
            StockLedger::query()
                ->where(
                    'reference_type',
                    'INVENTORY_ADJUSTMENT'
                )
                ->where(
                    'reference_id',
                    $adjustment->id
                )
                ->count();

        $journalCountBefore =
            Journal::query()
                ->where(
                    'reference_type',
                    'INVENTORY_ADJUSTMENT'
                )
                ->where(
                    'reference_id',
                    $adjustment->id
                )
                ->count();

        $service->post(
            $adjustment->id,
            $this->data['user_id']
        );

        $after =
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

        $this->assertEquals(
            $before['rows'][0][
                'qty_on_hand'
            ],
            $after['rows'][0][
                'qty_on_hand'
            ]
        );

        $this->assertEquals(
            $before['rows'][0][
                'inventory_value'
            ],
            $after['rows'][0][
                'inventory_value'
            ]
        );

        $this->assertSame(
            $ledgerCountBefore,
            StockLedger::query()
                ->where(
                    'reference_type',
                    'INVENTORY_ADJUSTMENT'
                )
                ->where(
                    'reference_id',
                    $adjustment->id
                )
                ->count()
        );

        $this->assertSame(
            $journalCountBefore,
            Journal::query()
                ->where(
                    'reference_type',
                    'INVENTORY_ADJUSTMENT'
                )
                ->where(
                    'reference_id',
                    $adjustment->id
                )
                ->count()
        );
    }
}
<?php

namespace Tests\Feature;

use App\DTO\CurrentStockFilterDTO;
use App\Models\User;
use App\Services\CurrentStockService;
use App\Services\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class InventoryStockTraceabilityTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    protected function setUp(): void
    {
        parent::setUp();

        $this->data =
            InventoryReconciliationTestData::create();

        Permission::firstOrCreate([
            'name' => 'inventory.current-stock.view',
            'guard_name' => 'web',
        ]);

        Permission::firstOrCreate([
            'name' => 'inventory.stock-ledger.view',
            'guard_name' => 'web',
        ]);

        app(
            \Spatie\Permission\PermissionRegistrar::class
        )->forgetCachedPermissions();
    }

    private function user(): User
    {
        return User::findOrFail(
            $this->data['user_id']
        );
    }

    public function test_current_stock_returns_latest_position():
        void
    {
        $result =
            app(CurrentStockService::class)
                ->report(
                    new CurrentStockFilterDTO(
                        warehouseId:
                            $this->data['warehouse_id'],

                        itemId:
                            $this->data['item_id'],
                    )
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

    public function test_current_stock_can_return_historical_position():
        void
    {
        $result =
            app(CurrentStockService::class)
                ->report(
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
            6000.0,
            $row['average_cost']
        );

        $this->assertEquals(
            1110000.0,
            $row['inventory_value']
        );
    }

    public function test_stock_ledger_closing_equals_current_stock():
        void
    {
        $currentStock =
            app(CurrentStockService::class)
                ->report(
                    new CurrentStockFilterDTO(
                        warehouseId:
                            $this->data['warehouse_id'],

                        itemId:
                            $this->data['item_id'],
                    )
                );

        $stock =
            $currentStock['rows'][0];

        $ledgerRows =
            app(StockLedgerService::class)
                ->getLedger(
                    warehouseId:
                        $this->data['warehouse_id'],

                    itemId:
                        $this->data['item_id'],
                );

        $last =
            $ledgerRows->last();

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

    public function test_historical_stock_ledger_equals_historical_current_stock():
        void
    {
        $currentStock =
            app(CurrentStockService::class)
                ->report(
                    new CurrentStockFilterDTO(
                        warehouseId:
                            $this->data['warehouse_id'],

                        itemId:
                            $this->data['item_id'],

                        asOfDate:
                            '2026-08-04',
                    )
                );

        $stock =
            $currentStock['rows'][0];

        $ledgerRows =
            app(StockLedgerService::class)
                ->getLedger(
                    warehouseId:
                        $this->data['warehouse_id'],

                    itemId:
                        $this->data['item_id'],

                    dateFrom:
                        null,

                    dateTo:
                        '2026-08-04',
                );

        $last =
            $ledgerRows->last();

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

    public function test_date_from_keeps_costing_context():
        void
    {
        $rows =
            app(StockLedgerService::class)
                ->getLedger(
                    warehouseId:
                        $this->data['warehouse_id'],

                    itemId:
                        $this->data['item_id'],

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

    public function test_guest_cannot_access_current_stock():
        void
    {
        $response =
            $this->get(
                route(
                    'erp.inventory.current-stock.index'
                )
            );

        $response->assertRedirect(
            route('login')
        );
    }

    public function test_guest_cannot_access_stock_ledger():
        void
    {
        $response =
            $this->get(
                route(
                    'erp.inventory.stock-ledger.index'
                )
            );

        $response->assertRedirect(
            route('login')
        );
    }

    public function test_user_without_permission_cannot_access_current_stock():
        void
    {
        $response =
            $this
                ->actingAs(
                    $this->user()
                )
                ->get(
                    route(
                        'erp.inventory.current-stock.index'
                    )
                );

        $response->assertForbidden();
    }

    public function test_user_without_permission_cannot_access_stock_ledger():
        void
    {
        $response =
            $this
                ->actingAs(
                    $this->user()
                )
                ->get(
                    route(
                        'erp.inventory.stock-ledger.index'
                    )
                );

        $response->assertForbidden();
    }

    public function test_user_with_permissions_can_access_current_stock_and_stock_ledger():
        void
    {
        $user =
            $this->user();

        $user->givePermissionTo([
            'inventory.current-stock.view',
            'inventory.stock-ledger.view',
        ]);

        $currentStockResponse =
            $this
                ->actingAs($user)
                ->get(
                    route(
                        'erp.inventory.current-stock.index'
                    )
                );

        $currentStockResponse
            ->assertOk()
            ->assertViewIs(
                'erp.inventory.current-stock.index'
            );

        $ledgerResponse =
            $this
                ->actingAs($user)
                ->get(
                    route(
                        'erp.inventory.stock-ledger.index',
                        [
                            'warehouse_id' =>
                                $this->data['warehouse_id'],

                            'item_id' =>
                                $this->data['item_id'],

                            'date_to' =>
                                '2026-08-04',
                        ]
                    )
                );

        $ledgerResponse
            ->assertOk()
            ->assertViewIs(
                'erp.inventory.stock-ledger.index'
            );
    }
}
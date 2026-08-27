<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class StockLedgerControllerTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    protected function setUp(): void
    {
        parent::setUp();

        $this->data =
            InventoryReconciliationTestData::create();

        Permission::firstOrCreate([
            'name' =>
                'inventory.stock-ledger.view',

            'guard_name' =>
                'web',
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

    public function test_user_with_permission_can_access_stock_ledger():
        void
    {
        $user =
            $this->user();

        $user->givePermissionTo(
            'inventory.stock-ledger.view'
        );

        $response =
            $this
                ->actingAs($user)
                ->get(
                    route(
                        'erp.inventory.stock-ledger.index'
                    )
                );

        $response->assertOk();

        $response->assertViewIs(
            'erp.inventory.stock-ledger.index'
        );

        $response->assertViewHas(
            'ledgers'
        );
    }

    public function test_stock_ledger_can_filter_by_warehouse_and_item():
        void
    {
        $user =
            $this->user();

        $user->givePermissionTo(
            'inventory.stock-ledger.view'
        );

        $response =
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
                        ]
                    )
                );

        $response->assertOk();

        $response->assertViewHas(
            'ledgers',
            function ($ledgers) {
                return $ledgers->isNotEmpty();
            }
        );
    }

    public function test_stock_ledger_rejects_invalid_date_range():
        void
    {
        $user =
            $this->user();

        $user->givePermissionTo(
            'inventory.stock-ledger.view'
        );

        $response =
            $this
                ->actingAs($user)
                ->get(
                    route(
                        'erp.inventory.stock-ledger.index',
                        [
                            'date_from' =>
                                '2026-08-31',

                            'date_to' =>
                                '2026-08-01',
                        ]
                    )
                );

        $response->assertSessionHasErrors(
            'date_to'
        );
    }

    public function test_stock_ledger_preserves_historical_costing_when_date_from_is_used():
        void
    {
        $user =
            $this->user();

        $user->givePermissionTo(
            'inventory.stock-ledger.view'
        );

        $response =
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

                            'date_from' =>
                                '2026-08-03',

                            'date_to' =>
                                '2026-08-31',
                        ]
                    )
                );

        $response->assertOk();

        $response->assertViewHas(
            'ledgers',
            function ($ledgers) {

                if ($ledgers->isEmpty()) {
                    return false;
                }

                $first =
                    $ledgers->first();

                return
                    $first[
                        'average_cost_before'
                    ]
                    >
                    0;
            }
        );
    }
}
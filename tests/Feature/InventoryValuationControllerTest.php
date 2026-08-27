<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class InventoryValuationControllerTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    protected function setUp(): void
    {
        parent::setUp();

        $this->data =
            InventoryReconciliationTestData::create();

        Permission::firstOrCreate([
            'name' => 'inventory.valuation.view',
            'guard_name' => 'web',
        ]);

        Permission::firstOrCreate([
            'name' => 'inventory.stock-ledger.view',
            'guard_name' => 'web',
        ]);

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }

    private function user(): User
    {
        return User::findOrFail(
            $this->data['user_id']
        );
    }

    private function giveValuationPermission(
        User $user
    ): void {
        $user->givePermissionTo(
            'inventory.valuation.view'
        );
    }

    public function test_guest_cannot_access_inventory_valuation():
        void
    {
        $response =
            $this->get(
                route(
                    'erp.inventory.valuation.index'
                )
            );

        $response->assertRedirect(
            route('login')
        );
    }

    public function test_user_without_permission_cannot_access_inventory_valuation():
        void
    {
        $response =
            $this
                ->actingAs(
                    $this->user()
                )
                ->get(
                    route(
                        'erp.inventory.valuation.index'
                    )
                );

        $response->assertForbidden();
    }

    public function test_user_with_permission_can_access_inventory_valuation():
        void
    {
        $user =
            $this->user();

        $this->giveValuationPermission(
            $user
        );

        $response =
            $this
                ->actingAs($user)
                ->get(
                    route(
                        'erp.inventory.valuation.index'
                    )
                );

        $response
            ->assertOk()
            ->assertViewIs(
                'erp.inventory.valuation.index'
            )
            ->assertViewHas('result')
            ->assertViewHas('warehouses')
            ->assertViewHas('items');
    }

    public function test_current_valuation_contains_correct_values():
        void
    {
        $user =
            $this->user();

        $this->giveValuationPermission(
            $user
        );

        $response =
            $this
                ->actingAs($user)
                ->get(
                    route(
                        'erp.inventory.valuation.index',
                        [
                            'warehouse_id' =>
                                $this->data[
                                    'warehouse_id'
                                ],

                            'item_id' =>
                                $this->data[
                                    'item_id'
                                ],
                        ]
                    )
                );

        $response->assertOk();

        $result =
            $response->viewData(
                'result'
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

    public function test_historical_as_of_date_returns_correct_valuation():
        void
    {
        $user =
            $this->user();

        $this->giveValuationPermission(
            $user
        );

        $response =
            $this
                ->actingAs($user)
                ->get(
                    route(
                        'erp.inventory.valuation.index',
                        [
                            'warehouse_id' =>
                                $this->data[
                                    'warehouse_id'
                                ],

                            'item_id' =>
                                $this->data[
                                    'item_id'
                                ],

                            'as_of_date' =>
                                '2026-08-04',
                        ]
                    )
                );

        $response->assertOk();

        $result =
            $response->viewData(
                'result'
            );

        $this->assertSame(
            '2026-08-04',
            $result['as_of_date']
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

    public function test_warehouse_filter_is_preserved():
        void
    {
        $user =
            $this->user();

        $this->giveValuationPermission(
            $user
        );

        $response =
            $this
                ->actingAs($user)
                ->get(
                    route(
                        'erp.inventory.valuation.index',
                        [
                            'warehouse_id' =>
                                $this->data[
                                    'warehouse_id'
                                ],
                        ]
                    )
                );

        $response->assertOk();

        $result =
            $response->viewData(
                'result'
            );

        $this->assertSame(
            $this->data['warehouse_id'],
            $result['warehouse_id']
        );
    }

    public function test_item_filter_is_preserved():
        void
    {
        $user =
            $this->user();

        $this->giveValuationPermission(
            $user
        );

        $response =
            $this
                ->actingAs($user)
                ->get(
                    route(
                        'erp.inventory.valuation.index',
                        [
                            'item_id' =>
                                $this->data[
                                    'item_id'
                                ],
                        ]
                    )
                );

        $response->assertOk();

        $result =
            $response->viewData(
                'result'
            );

        $this->assertSame(
            $this->data['item_id'],
            $result['item_id']
        );
    }

    public function test_invalid_warehouse_is_rejected():
        void
    {
        $user =
            $this->user();

        $this->giveValuationPermission(
            $user
        );

        $response =
            $this
                ->actingAs($user)
                ->get(
                    route(
                        'erp.inventory.valuation.index',
                        [
                            'warehouse_id' =>
                                999999,
                        ]
                    )
                );

        $response
            ->assertSessionHasErrors(
                'warehouse_id'
            );
    }

    public function test_invalid_item_is_rejected():
        void
    {
        $user =
            $this->user();

        $this->giveValuationPermission(
            $user
        );

        $response =
            $this
                ->actingAs($user)
                ->get(
                    route(
                        'erp.inventory.valuation.index',
                        [
                            'item_id' =>
                                999999,
                        ]
                    )
                );

        $response
            ->assertSessionHasErrors(
                'item_id'
            );
    }

    public function test_invalid_as_of_date_is_rejected():
        void
    {
        $user =
            $this->user();

        $this->giveValuationPermission(
            $user
        );

        $response =
            $this
                ->actingAs($user)
                ->get(
                    route(
                        'erp.inventory.valuation.index',
                        [
                            'as_of_date' =>
                                'invalid-date',
                        ]
                    )
                );

        $response
            ->assertSessionHasErrors(
                'as_of_date'
            );
    }
}
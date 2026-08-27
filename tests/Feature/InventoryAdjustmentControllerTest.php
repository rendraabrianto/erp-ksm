<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\DocumentSequence;
use App\Models\InventoryAdjustment;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Journal;
use App\Models\StockLedger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class InventoryAdjustmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    private int $gainAccountId;

    private int $lossAccountId;

    protected function setUp(): void
    {
        parent::setUp();

        /*
        |--------------------------------------------------------------------------
        | Base Fixture
        |--------------------------------------------------------------------------
        */

        $this->data =
            InventoryReconciliationTestData::create();

        /*
        |--------------------------------------------------------------------------
        | Permissions
        |--------------------------------------------------------------------------
        */

        foreach ([
            'inventory.adjustment.view',
            'inventory.adjustment.create',
            'inventory.adjustment.post',
        ] as $permission) {

            Permission::firstOrCreate([
                'name' =>
                    $permission,

                'guard_name' =>
                    'web',
            ]);
        }

        app(
            PermissionRegistrar::class
        )->forgetCachedPermissions();

        /*
        |--------------------------------------------------------------------------
        | Adjustment Accounting Setup
        |--------------------------------------------------------------------------
        */

        $existingAccount =
            Account::query()
                ->firstOrFail();

        $gainAccount =
            Account::firstOrCreate(
                [
                    'code' => '4901',
                ],
                [
                    'account_group_id' =>
                        $existingAccount
                            ->account_group_id,

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
                [
                    'code' => '6901',
                ],
                [
                    'account_group_id' =>
                        $existingAccount
                            ->account_group_id,

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

        $this->gainAccountId =
            (int) $gainAccount->id;

        $this->lossAccountId =
            (int) $lossAccount->id;

        /*
        |--------------------------------------------------------------------------
        | Map Category
        |--------------------------------------------------------------------------
        */

        $item =
            Item::findOrFail(
                $this->data['item_id']
            );

        ItemCategory::where(
            'id',
            $item->item_category_id
        )->update([
            'adjustment_gain_account_id' =>
                $this->gainAccountId,

            'adjustment_loss_account_id' =>
                $this->lossAccountId,
        ]);

        /*
        |--------------------------------------------------------------------------
        | ADJ Sequence
        |--------------------------------------------------------------------------
        */

        DocumentSequence::firstOrCreate(
            [
                'document_type' =>
                    'ADJ',
            ],
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

    private function user(): User
    {
        return User::findOrFail(
            $this->data['user_id']
        );
    }

    private function payload(
        float $physicalQty = 170.0
    ): array {

        return [
            'warehouse_id' =>
                $this->data['warehouse_id'],

            'adjustment_date' =>
                '2026-08-23',

            'reason' =>
                'STOCK_OPNAME',

            'remarks' =>
                'Controller test',

            'details' => [
                [
                    'item_id' =>
                        $this->data['item_id'],

                    'physical_qty' =>
                        $physicalQty,

                    'remarks' =>
                        'Stock opname test',
                ],
            ],
        ];
    }

    private function createDraft(
        User $user,
        float $physicalQty = 170.0
    ): InventoryAdjustment {

        $user->givePermissionTo(
            'inventory.adjustment.create'
        );

        $this
            ->actingAs($user)
            ->post(
                route(
                    'erp.inventory.adjustment.store'
                ),
                $this->payload(
                    $physicalQty
                )
            );

        return InventoryAdjustment::query()
            ->latest('id')
            ->firstOrFail();
    }

    /*
    |--------------------------------------------------------------------------
    | SECURITY
    |--------------------------------------------------------------------------
    */

    public function test_guest_cannot_access_adjustment_index():
        void
    {
        $response =
            $this->get(
                route(
                    'erp.inventory.adjustment.index'
                )
            );

        $response->assertRedirect(
            route('login')
        );
    }

    public function test_user_without_view_permission_cannot_access_index():
        void
    {
        $response =
            $this
                ->actingAs(
                    $this->user()
                )
                ->get(
                    route(
                        'erp.inventory.adjustment.index'
                    )
                );

        $response->assertForbidden();
    }

    public function test_user_with_view_permission_can_access_index():
        void
    {
        $user =
            $this->user();

        $user->givePermissionTo(
            'inventory.adjustment.view'
        );

        $response =
            $this
                ->actingAs($user)
                ->get(
                    route(
                        'erp.inventory.adjustment.index'
                    )
                );

        $response
            ->assertOk()
            ->assertViewIs(
                'erp.inventory.adjustment.index'
            )
            ->assertViewHas(
                'adjustments'
            )
            ->assertViewHas(
                'warehouses'
            );
    }

    public function test_user_without_create_permission_cannot_access_create():
        void
    {
        $response =
            $this
                ->actingAs(
                    $this->user()
                )
                ->get(
                    route(
                        'erp.inventory.adjustment.create'
                    )
                );

        $response->assertForbidden();
    }

    public function test_user_with_create_permission_can_access_create():
        void
    {
        $user =
            $this->user();

        $user->givePermissionTo(
            'inventory.adjustment.create'
        );

        $response =
            $this
                ->actingAs($user)
                ->get(
                    route(
                        'erp.inventory.adjustment.create'
                    )
                );

        $response
            ->assertOk()
            ->assertViewIs(
                'erp.inventory.adjustment.create'
            )
            ->assertViewHas(
                'warehouses'
            )
            ->assertViewHas(
                'items'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE DRAFT
    |--------------------------------------------------------------------------
    */

    public function test_user_can_create_inventory_adjustment_draft():
        void
    {
        $user =
            $this->user();

        $user->givePermissionTo(
            'inventory.adjustment.create'
        );

        $response =
            $this
                ->actingAs($user)
                ->post(
                    route(
                        'erp.inventory.adjustment.store'
                    ),
                    $this->payload()
                );

        $adjustment =
            InventoryAdjustment::query()
                ->firstOrFail();

        $response->assertRedirect(
            route(
                'erp.inventory.adjustment.show',
                $adjustment
            )
        );

        $this->assertSame(
            'DRAFT',
            $adjustment->status
        );

        $this->assertDatabaseHas(
            'inventory_adjustments',
            [
                'id' =>
                    $adjustment->id,

                'status' =>
                    'DRAFT',

                'warehouse_id' =>
                    $this->data[
                        'warehouse_id'
                    ],
            ]
        );

        $this->assertDatabaseHas(
            'inventory_adjustment_details',
            [
                'inventory_adjustment_id' =>
                    $adjustment->id,

                'item_id' =>
                    $this->data[
                        'item_id'
                    ],

                'system_qty' =>
                    173.0000,

                'physical_qty' =>
                    170.0000,

                'adjustment_qty' =>
                    -3.0000,
            ]
        );

        /*
        | DRAFT tidak boleh posting inventory/journal.
        */

        $this->assertDatabaseMissing(
            'stock_ledgers',
            [
                'reference_type' =>
                    'INVENTORY_ADJUSTMENT',

                'reference_id' =>
                    $adjustment->id,
            ]
        );

        $this->assertDatabaseMissing(
            'journals',
            [
                'reference_type' =>
                    'INVENTORY_ADJUSTMENT',

                'reference_id' =>
                    $adjustment->id,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    public function test_store_rejects_missing_details():
        void
    {
        $user =
            $this->user();

        $user->givePermissionTo(
            'inventory.adjustment.create'
        );

        $payload =
            $this->payload();

        $payload['details'] =
            [];

        $response =
            $this
                ->actingAs($user)
                ->post(
                    route(
                        'erp.inventory.adjustment.store'
                    ),
                    $payload
                );

        $response->assertSessionHasErrors(
            'details'
        );

        $this->assertSame(
            0,
            InventoryAdjustment::count()
        );
    }

    public function test_store_rejects_negative_physical_quantity():
        void
    {
        $user =
            $this->user();

        $user->givePermissionTo(
            'inventory.adjustment.create'
        );

        $response =
            $this
                ->actingAs($user)
                ->post(
                    route(
                        'erp.inventory.adjustment.store'
                    ),
                    $this->payload(
                        -1
                    )
                );

        $response->assertSessionHasErrors(
            'details.0.physical_qty'
        );

        $this->assertSame(
            0,
            InventoryAdjustment::count()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function test_user_with_view_permission_can_view_draft():
        void
    {
        $user =
            $this->user();

        $adjustment =
            $this->createDraft(
                $user
            );

        $user->givePermissionTo(
            'inventory.adjustment.view'
        );

        $response =
            $this
                ->actingAs($user)
                ->get(
                    route(
                        'erp.inventory.adjustment.show',
                        $adjustment
                    )
                );

        $response
            ->assertOk()
            ->assertViewIs(
                'erp.inventory.adjustment.show'
            )
            ->assertViewHas(
                'adjustment'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | POST PERMISSION
    |--------------------------------------------------------------------------
    */

    public function test_user_without_post_permission_cannot_post_adjustment():
        void
    {
        $user =
            $this->user();

        $adjustment =
            $this->createDraft(
                $user
            );

        $response =
            $this
                ->actingAs($user)
                ->post(
                    route(
                        'erp.inventory.adjustment.post',
                        $adjustment
                    )
                );

        $response->assertForbidden();

        $adjustment->refresh();

        $this->assertSame(
            'DRAFT',
            $adjustment->status
        );
    }

    /*
    |--------------------------------------------------------------------------
    | POST OUT
    |--------------------------------------------------------------------------
    */

    public function test_user_with_post_permission_can_post_adjustment():
        void
    {
        $user =
            $this->user();

        $adjustment =
            $this->createDraft(
                $user,
                170.0
            );

        $user->givePermissionTo(
            'inventory.adjustment.post'
        );

        $response =
            $this
                ->actingAs($user)
                ->post(
                    route(
                        'erp.inventory.adjustment.post',
                        $adjustment
                    )
                );

        $response->assertRedirect(
            route(
                'erp.inventory.adjustment.show',
                $adjustment
            )
        );

        $adjustment->refresh();

        $this->assertSame(
            'POSTED',
            $adjustment->status
        );

        $this->assertSame(
            $user->id,
            $adjustment->posted_by
        );

        $this->assertNotNull(
            $adjustment->posted_at
        );

        $this->assertDatabaseHas(
            'stock_ledgers',
            [
                'reference_type' =>
                    'INVENTORY_ADJUSTMENT',

                'reference_id' =>
                    $adjustment->id,

                'qty_out' =>
                    3.0000,

                'balance_qty' =>
                    170.0000,
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

    /*
    |--------------------------------------------------------------------------
    | ACCOUNTING THROUGH CONTROLLER
    |--------------------------------------------------------------------------
    */

    public function test_controller_post_creates_balanced_adjustment_journal():
        void
    {
        $user =
            $this->user();

        $adjustment =
            $this->createDraft(
                $user,
                170.0
            );

        $user->givePermissionTo(
            'inventory.adjustment.post'
        );

        $this
            ->actingAs($user)
            ->post(
                route(
                    'erp.inventory.adjustment.post',
                    $adjustment
                )
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

        $this->assertEquals(
            (float)
            $journal->details
                ->sum('debit'),

            (float)
            $journal->details
                ->sum('credit')
        );

        $lossLine =
            $journal
                ->details
                ->firstWhere(
                    'account_id',
                    $this->lossAccountId
                );

        $inventoryLine =
            $journal
                ->details
                ->firstWhere(
                    'account_id',
                    $this->data[
                        'inventory_account_id'
                    ]
                );

        $this->assertNotNull(
            $lossLine
        );

        $this->assertNotNull(
            $inventoryLine
        );

        $this->assertEquals(
            18000.0,
            (float)
            $lossLine->debit
        );

        $this->assertEquals(
            18000.0,
            (float)
            $inventoryLine->credit
        );
    }

    /*
    |--------------------------------------------------------------------------
    | IDEMPOTENCY THROUGH WEB
    |--------------------------------------------------------------------------
    */

    public function test_second_controller_post_is_idempotent():
        void
    {
        $user =
            $this->user();

        $adjustment =
            $this->createDraft(
                $user,
                170.0
            );

        $user->givePermissionTo(
            'inventory.adjustment.post'
        );

        $this
            ->actingAs($user)
            ->post(
                route(
                    'erp.inventory.adjustment.post',
                    $adjustment
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

        /*
        | POST kedua.
        */

        $this
            ->actingAs($user)
            ->post(
                route(
                    'erp.inventory.adjustment.post',
                    $adjustment
                )
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
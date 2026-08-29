<?php

namespace Tests\Feature;

use App\DTO\InventoryTransferCreateDTO;
use App\Models\DocumentSequence;
use App\Models\InventoryTransfer;
use App\Models\StockLedger;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class InventoryTransferHttpTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    private User $user;

    private Warehouse $sourceWarehouse;

    private Warehouse $destinationWarehouse;

    protected function setUp(): void
    {
        parent::setUp();

        /*
        |--------------------------------------------------------------------------
        | Base Inventory Fixture
        |--------------------------------------------------------------------------
        */

        $this->data =
            InventoryReconciliationTestData::create();

        $this->user =
            User::findOrFail(
                $this->data['user_id']
            );

        $this->sourceWarehouse =
            Warehouse::findOrFail(
                $this->data['warehouse_id']
            );

        /*
        |--------------------------------------------------------------------------
        | Destination Warehouse
        |--------------------------------------------------------------------------
        */

        $this->destinationWarehouse =
            Warehouse::create([
                'company_id' =>
                    $this->data['company_id'],

                'branch_id' =>
                    $this->data['branch_id'],

                'code' =>
                    'WH-HTTP-DEST',

                'name' =>
                    'HTTP Transfer Destination',

                'is_active' =>
                    true,
            ]);

        /*
        |--------------------------------------------------------------------------
        | TRF Document Sequence
        |--------------------------------------------------------------------------
        */

        DocumentSequence::firstOrCreate(
            [
                'document_type' =>
                    'TRF',
            ],
            [
                'prefix' =>
                    'TRF',

                'description' =>
                    'Inventory Transfer',

                'current_number' =>
                    0,

                'padding' =>
                    5,

                'is_active' =>
                    true,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Permissions
        |--------------------------------------------------------------------------
        */

        foreach ([
            'inventory.transfer.view',
            'inventory.transfer.create',
            'inventory.transfer.post',
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
        | Make Authorization Deterministic For These Tests
        |--------------------------------------------------------------------------
        */

        $this->user->syncRoles([]);

        $this->user->syncPermissions([
            'inventory.transfer.view',
            'inventory.transfer.create',
            'inventory.transfer.post',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper - Create Draft
    |--------------------------------------------------------------------------
    */

    private function createDraft(
        float $qty = 20.0
    ): InventoryTransfer {

        return app(
            InventoryTransferService::class
        )->create(
            new InventoryTransferCreateDTO(
                sourceWarehouseId:
                    $this->sourceWarehouse->id,

                destinationWarehouseId:
                    $this->destinationWarehouse->id,

                transferDate:
                    '2026-08-27',

                remarks:
                    'HTTP transfer test',

                createdBy:
                    $this->user->id,

                details: [
                    [
                        'item_id' =>
                            $this->data['item_id'],

                        'qty' =>
                            $qty,

                        'remarks' =>
                            'HTTP item',
                    ],
                ],
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function test_user_with_view_permission_can_open_transfer_index():
        void
    {
        $response =
            $this
                ->actingAs($this->user)
                ->get(
                    route(
                        'erp.inventory.transfer.index'
                    )
                );

        $response
            ->assertOk()
            ->assertViewIs(
                'erp.inventory.transfer.index'
            )
            ->assertViewHas(
                'transfers'
            )
            ->assertViewHas(
                'warehouses'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

    public function test_user_with_create_permission_can_open_create_page():
        void
    {
        $response =
            $this
                ->actingAs($this->user)
                ->get(
                    route(
                        'erp.inventory.transfer.create'
                    )
                );

        $response
            ->assertOk()
            ->assertViewIs(
                'erp.inventory.transfer.create'
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
    | STORE
    |--------------------------------------------------------------------------
    */

    public function test_store_creates_inventory_transfer_draft():
        void
    {
        $response =
            $this
                ->actingAs($this->user)
                ->post(
                    route(
                        'erp.inventory.transfer.store'
                    ),
                    [
                        'source_warehouse_id' =>
                            $this->sourceWarehouse->id,

                        'destination_warehouse_id' =>
                            $this->destinationWarehouse->id,

                        'transfer_date' =>
                            '2026-08-27',

                        'remarks' =>
                            'Created through HTTP',

                        'details' => [
                            [
                                'item_id' =>
                                    $this->data['item_id'],

                                'qty' =>
                                    20,

                                'remarks' =>
                                    'HTTP detail',
                            ],
                        ],
                    ]
                );

        $transfer =
            InventoryTransfer::query()
                ->latest('id')
                ->firstOrFail();

        $response
            ->assertRedirect(
                route(
                    'erp.inventory.transfer.show',
                    $transfer
                )
            );

        $this->assertDatabaseHas(
            'inventory_transfers',
            [
                'id' =>
                    $transfer->id,

                'source_warehouse_id' =>
                    $this->sourceWarehouse->id,

                'destination_warehouse_id' =>
                    $this->destinationWarehouse->id,

                'status' =>
                    'DRAFT',

                'created_by' =>
                    $this->user->id,
            ]
        );

        $this->assertDatabaseHas(
            'inventory_transfer_details',
            [
                'inventory_transfer_id' =>
                    $transfer->id,

                'item_id' =>
                    $this->data['item_id'],

                'qty' =>
                    20,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | DRAFT Must Not Create Stock Movement
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseMissing(
            'stock_ledgers',
            [
                'reference_type' =>
                    'INVENTORY_TRANSFER',

                'reference_id' =>
                    $transfer->id,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function test_show_displays_inventory_transfer_document():
        void
    {
        $transfer =
            $this->createDraft();

        $response =
            $this
                ->actingAs($this->user)
                ->get(
                    route(
                        'erp.inventory.transfer.show',
                        $transfer
                    )
                );

        $response
            ->assertOk()
            ->assertViewIs(
                'erp.inventory.transfer.show'
            )
            ->assertViewHas(
                'transfer'
            )
            ->assertViewHas(
                'stockLedgers'
            )
            ->assertSee(
                $transfer->transfer_no
            )
            ->assertSee(
                'HTTP Transfer Destination'
            )
            ->assertSee(
                'DRAFT'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | POST
    |--------------------------------------------------------------------------
    */

    public function test_http_post_marks_transfer_as_posted_and_creates_two_ledgers():
        void
    {
        $transfer =
            $this->createDraft(
                20.0
            );

        $response =
            $this
                ->actingAs($this->user)
                ->post(
                    route(
                        'erp.inventory.transfer.post',
                        $transfer
                    )
                );

        $response
            ->assertRedirect(
                route(
                    'erp.inventory.transfer.show',
                    $transfer
                )
            );

        $transfer->refresh();

        $this->assertSame(
            'POSTED',
            $transfer->status
        );

        $this->assertSame(
            $this->user->id,
            (int) $transfer->posted_by
        );

        $this->assertNotNull(
            $transfer->posted_at
        );

        /*
        |--------------------------------------------------------------------------
        | Exactly Two Transfer Ledgers
        |--------------------------------------------------------------------------
        */

        $ledgers =
            StockLedger::query()
                ->where(
                    'reference_type',
                    'INVENTORY_TRANSFER'
                )
                ->where(
                    'reference_id',
                    $transfer->id
                )
                ->orderBy('id')
                ->get();

        $this->assertCount(
            2,
            $ledgers
        );

        /*
        |--------------------------------------------------------------------------
        | Source OUT
        |--------------------------------------------------------------------------
        */

        $sourceLedger =
            $ledgers->firstWhere(
                'warehouse_id',
                $this->sourceWarehouse->id
            );

        $this->assertNotNull(
            $sourceLedger
        );

        $this->assertEquals(
            20.0,
            (float) $sourceLedger->qty_out
        );

        $this->assertEquals(
            0.0,
            (float) $sourceLedger->qty_in
        );

        /*
        |--------------------------------------------------------------------------
        | Destination IN
        |--------------------------------------------------------------------------
        */

        $destinationLedger =
            $ledgers->firstWhere(
                'warehouse_id',
                $this->destinationWarehouse->id
            );

        $this->assertNotNull(
            $destinationLedger
        );

        $this->assertEquals(
            20.0,
            (float) $destinationLedger->qty_in
        );

        $this->assertEquals(
            0.0,
            (float) $destinationLedger->qty_out
        );

        /*
        |--------------------------------------------------------------------------
        | Transfer Value Must Be Identical
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            (float) $sourceLedger->unit_cost,
            (float) $destinationLedger->unit_cost
        );

        $this->assertEquals(
            (float) $sourceLedger->total_cost,
            (float) $destinationLedger->total_cost
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PERMISSION
    |--------------------------------------------------------------------------
    */

    public function test_user_without_view_permission_cannot_open_transfer_index():
        void
    {
        $this->user->syncPermissions([
            'inventory.transfer.create',
            'inventory.transfer.post',
        ]);

        app(
            PermissionRegistrar::class
        )->forgetCachedPermissions();

        $response =
            $this
                ->actingAs($this->user)
                ->get(
                    route(
                        'erp.inventory.transfer.index'
                    )
                );

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | IDEMPOTENT HTTP POST
    |--------------------------------------------------------------------------
    */

    public function test_second_http_post_does_not_duplicate_stock_ledgers():
        void
    {
        $transfer =
            $this->createDraft(
                20.0
            );

        /*
        |--------------------------------------------------------------------------
        | First POST
        |--------------------------------------------------------------------------
        */

        $this
            ->actingAs($this->user)
            ->post(
                route(
                    'erp.inventory.transfer.post',
                    $transfer
                )
            )
            ->assertRedirect();

        /*
        |--------------------------------------------------------------------------
        | Second POST
        |--------------------------------------------------------------------------
        */

        $this
            ->actingAs($this->user)
            ->post(
                route(
                    'erp.inventory.transfer.post',
                    $transfer
                )
            )
            ->assertRedirect();

        /*
        |--------------------------------------------------------------------------
        | Still Exactly Two Ledgers
        |--------------------------------------------------------------------------
        */

        $ledgerCount =
            StockLedger::query()
                ->where(
                    'reference_type',
                    'INVENTORY_TRANSFER'
                )
                ->where(
                    'reference_id',
                    $transfer->id
                )
                ->count();

        $this->assertSame(
            2,
            $ledgerCount
        );

        $transfer->refresh();

        $this->assertSame(
            'POSTED',
            $transfer->status
        );
    }
}
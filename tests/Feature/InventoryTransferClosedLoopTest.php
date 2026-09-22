<?php

namespace Tests\Feature;

use App\DTO\InventoryTransactionDTO;
use App\DTO\InventoryTransferCreateDTO;
use App\Models\DocumentSequence;
use App\Models\InventoryTransferDetail;
use App\Models\StockLedger;
use App\Models\Warehouse;
use App\Services\InventoryCostingService;
use App\Services\InventoryTransactionService;
use App\Services\InventoryTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;
use App\Models\Branch;
use App\Models\Company;
use App\Models\InventoryTransfer;
use App\Models\User;
use RuntimeException;

class InventoryTransferClosedLoopTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    private Warehouse $sourceWarehouse;

    private Warehouse $destinationWarehouse;

    private InventoryTransferService $transferService;

    private InventoryCostingService $costingService;

    protected function setUp(): void
    {
        parent::setUp();

        /*
        |--------------------------------------------------------------------------
        | Base Inventory Fixture
        |--------------------------------------------------------------------------
        |
        | Closing source fixture:
        |
        | qty   = 173
        | value = 1,038,000
        | avg   = 6,000
        |
        */

        $this->data =
            InventoryReconciliationTestData::create();

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
                    'WH-TRANSFER-CLOSED-LOOP',

                'name' =>
                    'Transfer Closed Loop Destination',

                'is_active' =>
                    true,
            ]);

        /*
        |--------------------------------------------------------------------------
        | TRF Sequence
        |--------------------------------------------------------------------------
        */

        DocumentSequence::firstOrCreate(
            [
                'company_id' =>
                    $this->data['company_id'],
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

        $this->transferService =
            app(
                InventoryTransferService::class
            );

        $this->costingService =
            app(
                InventoryCostingService::class
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Helper - Create Transfer
    |--------------------------------------------------------------------------
    */

    private function createTransfer(
        float $qty = 20.0
    ) {
        return
            $this
                ->transferService
                ->create(
                    new InventoryTransferCreateDTO(
                        sourceWarehouseId:
                            $this->sourceWarehouse->id,

                        destinationWarehouseId:
                            $this->destinationWarehouse->id,

                        transferDate:
                            '2026-08-27',

                        remarks:
                            'Closed loop transfer test',

                        createdBy:
                            $this->data['user_id'],

                        details: [
                            [
                                'item_id' =>
                                    $this->data['item_id'],

                                'qty' =>
                                    $qty,

                                'remarks' =>
                                    'Closed loop item',
                            ],
                        ],
                    )
                );
    }

    /*
    |--------------------------------------------------------------------------
    | Helper - Inventory State
    |--------------------------------------------------------------------------
    */

    private function state(
        int $warehouseId
    ): array {
        return
            $this
                ->costingService
                ->getCurrentState(
                    $warehouseId,
                    $this->data['item_id']
                );
    }

    /*
    |--------------------------------------------------------------------------
    | 1. COMPANY INVENTORY VALUE MUST NOT CHANGE
    |--------------------------------------------------------------------------
    */

    public function test_transfer_preserves_total_inventory_value():
        void
    {
        $sourceBefore =
            $this->state(
                $this->sourceWarehouse->id
            );

        $destinationBefore =
            $this->state(
                $this->destinationWarehouse->id
            );

        $totalBefore =
            (float) $sourceBefore['value']
            +
            (float) $destinationBefore['value'];

        /*
        |--------------------------------------------------------------------------
        | Sanity Check Before
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            173.0,
            (float) $sourceBefore['qty']
        );

        $this->assertEquals(
            1038000.00,
            (float) $sourceBefore['value']
        );

        $this->assertEquals(
            6000.00,
            (float) $sourceBefore['average_cost']
        );

        $this->assertEquals(
            0.0,
            (float) $destinationBefore['qty']
        );

        $this->assertEquals(
            0.0,
            (float) $destinationBefore['value']
        );

        /*
        |--------------------------------------------------------------------------
        | Transfer 20
        |--------------------------------------------------------------------------
        */

        $transfer =
            $this->createTransfer(
                20.0
            );

        $this
            ->transferService
            ->post(
                $transfer->id,
                $this->data['user_id']
            );

        /*
        |--------------------------------------------------------------------------
        | State After
        |--------------------------------------------------------------------------
        */

        $sourceAfter =
            $this->state(
                $this->sourceWarehouse->id
            );

        $destinationAfter =
            $this->state(
                $this->destinationWarehouse->id
            );

        $totalAfter =
            (float) $sourceAfter['value']
            +
            (float) $destinationAfter['value'];

        /*
        |--------------------------------------------------------------------------
        | Expected Closing Position
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            153.0,
            (float) $sourceAfter['qty']
        );

        $this->assertEquals(
            918000.00,
            (float) $sourceAfter['value']
        );

        $this->assertEquals(
            20.0,
            (float) $destinationAfter['qty']
        );

        $this->assertEquals(
            120000.00,
            (float) $destinationAfter['value']
        );

        /*
        |--------------------------------------------------------------------------
        | CLOSED LOOP
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            1038000.00,
            $totalBefore
        );

        $this->assertEquals(
            $totalBefore,
            $totalAfter
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 2. SOURCE VALUE DECREASE = TRANSFER VALUE
    |--------------------------------------------------------------------------
    */

    public function test_source_value_decreases_by_transfer_value():
        void
    {
        $sourceBefore =
            $this->state(
                $this->sourceWarehouse->id
            );

        $transfer =
            $this->createTransfer(
                20.0
            );

        $posted =
            $this
                ->transferService
                ->post(
                    $transfer->id,
                    $this->data['user_id']
                );

        $sourceAfter =
            $this->state(
                $this->sourceWarehouse->id
            );

        $detail =
            $posted
                ->details
                ->first();

        $sourceValueDecrease =
            (float) $sourceBefore['value']
            -
            (float) $sourceAfter['value'];

        $this->assertEquals(
            120000.00,
            $sourceValueDecrease
        );

        $this->assertEquals(
            (float) $detail->total_cost,
            $sourceValueDecrease
        );

        $this->assertEquals(
            6000.00,
            (float) $detail->unit_cost
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 3. DESTINATION VALUE INCREASE = TRANSFER VALUE
    |--------------------------------------------------------------------------
    */

    public function test_destination_value_increases_by_transfer_value():
        void
    {
        $destinationBefore =
            $this->state(
                $this->destinationWarehouse->id
            );

        $transfer =
            $this->createTransfer(
                20.0
            );

        $posted =
            $this
                ->transferService
                ->post(
                    $transfer->id,
                    $this->data['user_id']
                );

        $destinationAfter =
            $this->state(
                $this->destinationWarehouse->id
            );

        $detail =
            $posted
                ->details
                ->first();

        $destinationValueIncrease =
            (float) $destinationAfter['value']
            -
            (float) $destinationBefore['value'];

        $this->assertEquals(
            120000.00,
            $destinationValueIncrease
        );

        $this->assertEquals(
            (float) $detail->total_cost,
            $destinationValueIncrease
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 4. SOURCE AND DESTINATION LEDGER MUST RECONCILE
    |--------------------------------------------------------------------------
    */

    public function test_source_and_destination_ledger_values_reconcile():
        void
    {
        $transfer =
            $this->createTransfer(
                20.0
            );

        $this
            ->transferService
            ->post(
                $transfer->id,
                $this->data['user_id']
            );

        $sourceLedger =
            StockLedger::query()
                ->where(
                    'warehouse_id',
                    $this->sourceWarehouse->id
                )
                ->where(
                    'item_id',
                    $this->data['item_id']
                )
                ->where(
                    'reference_type',
                    'INVENTORY_TRANSFER'
                )
                ->where(
                    'reference_id',
                    $transfer->id
                )
                ->where(
                    'qty_out',
                    '>',
                    0
                )
                ->firstOrFail();

        $destinationLedger =
            StockLedger::query()
                ->where(
                    'warehouse_id',
                    $this->destinationWarehouse->id
                )
                ->where(
                    'item_id',
                    $this->data['item_id']
                )
                ->where(
                    'reference_type',
                    'INVENTORY_TRANSFER'
                )
                ->where(
                    'reference_id',
                    $transfer->id
                )
                ->where(
                    'qty_in',
                    '>',
                    0
                )
                ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Quantity
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            20.0,
            (float) $sourceLedger->qty_out
        );

        $this->assertEquals(
            20.0,
            (float) $destinationLedger->qty_in
        );

        /*
        |--------------------------------------------------------------------------
        | Unit Cost
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            6000.00,
            (float) $sourceLedger->unit_cost
        );

        $this->assertEquals(
            (float) $sourceLedger->unit_cost,
            (float) $destinationLedger->unit_cost
        );

        /*
        |--------------------------------------------------------------------------
        | Transfer Value
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            120000.00,
            (float) $sourceLedger->total_cost
        );

        $this->assertEquals(
            (float) $sourceLedger->total_cost,
            (float) $destinationLedger->total_cost
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 5. POSTED DETAIL MUST MATCH ACTUAL LEDGER VALUE
    |--------------------------------------------------------------------------
    */

    public function test_posted_detail_equals_actual_transferred_value():
        void
    {
        $transfer =
            $this->createTransfer(
                20.0
            );

        $this
            ->transferService
            ->post(
                $transfer->id,
                $this->data['user_id']
            );

        $detail =
            InventoryTransferDetail::query()
                ->where(
                    'inventory_transfer_id',
                    $transfer->id
                )
                ->where(
                    'item_id',
                    $this->data['item_id']
                )
                ->firstOrFail();

        $sourceLedger =
            StockLedger::query()
                ->where(
                    'warehouse_id',
                    $this->sourceWarehouse->id
                )
                ->where(
                    'reference_type',
                    'INVENTORY_TRANSFER'
                )
                ->where(
                    'reference_id',
                    $transfer->id
                )
                ->where(
                    'qty_out',
                    '>',
                    0
                )
                ->firstOrFail();

        $destinationLedger =
            StockLedger::query()
                ->where(
                    'warehouse_id',
                    $this->destinationWarehouse->id
                )
                ->where(
                    'reference_type',
                    'INVENTORY_TRANSFER'
                )
                ->where(
                    'reference_id',
                    $transfer->id
                )
                ->where(
                    'qty_in',
                    '>',
                    0
                )
                ->firstOrFail();

        $this->assertEquals(
            6000.00,
            (float) $detail->unit_cost
        );

        $this->assertEquals(
            120000.00,
            (float) $detail->total_cost
        );

        $this->assertEquals(
            (float) $sourceLedger->unit_cost,
            (float) $detail->unit_cost
        );

        $this->assertEquals(
            (float) $sourceLedger->total_cost,
            (float) $detail->total_cost
        );

        $this->assertEquals(
            (float) $destinationLedger->unit_cost,
            (float) $detail->unit_cost
        );

        $this->assertEquals(
            (float) $destinationLedger->total_cost,
            (float) $detail->total_cost
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 6. DESTINATION WITH DIFFERENT EXISTING AVERAGE COST
    |--------------------------------------------------------------------------
    |
    | Destination awal:
    |
    | 10 @ 8,000 = 80,000
    |
    | Transfer:
    |
    | 20 @ 6,000 = 120,000
    |
    | Destination akhir:
    |
    | Qty   = 30
    | Value = 200,000
    | Avg   = 6,666.67
    |
    | Transfer cost TETAP 6,000.
    |--------------------------------------------------------------------------
    */

    public function test_destination_recalculates_moving_average_without_changing_transfer_cost():
        void
    {
        /*
        |--------------------------------------------------------------------------
        | Seed Destination Stock
        |--------------------------------------------------------------------------
        */

        app(
            InventoryTransactionService::class
        )->post(
            new InventoryTransactionDTO(
                warehouseId:
                    $this->destinationWarehouse->id,

                itemId:
                    $this->data['item_id'],

                referenceType:
                    'TEST_DESTINATION_OPENING',

                referenceId:
                    900001,

                qtyIn:
                    10.0,

                qtyOut:
                    0.0,

                unitCost:
                    8000.0,

                remarks:
                    'Destination initial stock',

                transactionDate:
                    '2026-08-20',
            )
        );

        /*
        |--------------------------------------------------------------------------
        | State Before Transfer
        |--------------------------------------------------------------------------
        */

        $sourceBefore =
            $this->state(
                $this->sourceWarehouse->id
            );

        $destinationBefore =
            $this->state(
                $this->destinationWarehouse->id
            );

        $totalBefore =
            (float) $sourceBefore['value']
            +
            (float) $destinationBefore['value'];

        $this->assertEquals(
            10.0,
            (float) $destinationBefore['qty']
        );

        $this->assertEquals(
            80000.00,
            (float) $destinationBefore['value']
        );

        $this->assertEquals(
            8000.00,
            (float) $destinationBefore['average_cost']
        );

        /*
        |--------------------------------------------------------------------------
        | Post Transfer
        |--------------------------------------------------------------------------
        */

        $transfer =
            $this->createTransfer(
                20.0
            );

        $posted =
            $this
                ->transferService
                ->post(
                    $transfer->id,
                    $this->data['user_id']
                );

        /*
        |--------------------------------------------------------------------------
        | State After
        |--------------------------------------------------------------------------
        */

        $sourceAfter =
            $this->state(
                $this->sourceWarehouse->id
            );

        $destinationAfter =
            $this->state(
                $this->destinationWarehouse->id
            );

        $totalAfter =
            (float) $sourceAfter['value']
            +
            (float) $destinationAfter['value'];

        /*
        |--------------------------------------------------------------------------
        | Source
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            153.0,
            (float) $sourceAfter['qty']
        );

        $this->assertEquals(
            918000.00,
            (float) $sourceAfter['value']
        );

        /*
        |--------------------------------------------------------------------------
        | Destination Moving Average
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            30.0,
            (float) $destinationAfter['qty']
        );

        $this->assertEquals(
            200000.00,
            (float) $destinationAfter['value']
        );

        $this->assertEqualsWithDelta(
            6666.67,
            (float) $destinationAfter['average_cost'],
            0.01
        );

        /*
        |--------------------------------------------------------------------------
        | Transfer Cost Must NOT Become Destination Average Cost
        |--------------------------------------------------------------------------
        */

        $detail =
            $posted
                ->details
                ->first();

        $this->assertEquals(
            6000.00,
            (float) $detail->unit_cost
        );

        $this->assertEquals(
            120000.00,
            (float) $detail->total_cost
        );

        /*
        |--------------------------------------------------------------------------
        | Company Value Invariant
        |--------------------------------------------------------------------------
        |
        | BEFORE:
        |
        | Source      = 1,038,000
        | Destination =    80,000
        | Total       = 1,118,000
        |
        | AFTER:
        |
        | Source      =   918,000
        | Destination =   200,000
        | Total       = 1,118,000
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            1118000.00,
            $totalBefore
        );

        $this->assertEquals(
            $totalBefore,
            $totalAfter
        );
    }

    public function test_inventory_transfer_inherits_company_from_source_warehouse():
        void
    {
        /*
        |--------------------------------------------------------------------------
        | Source Warehouse Company Is Authoritative
        |--------------------------------------------------------------------------
        |
        | Inventory Transfer company ownership must come from source warehouse.
        |
        | Source and destination warehouses must belong to the same company,
        | and after G6 the creator must belong to that company as well.
        |
        | Cross-company actor rejection is tested separately in
        | InventoryCompanyGuardTest.
        |
        */

        $companyAId =
            (int) $this->sourceWarehouse->company_id;

        $creator =
            User::findOrFail(
                $this->data['user_id']
            );

        /*
        |--------------------------------------------------------------------------
        | Sanity Check
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $companyAId,
            (int) $this
                ->destinationWarehouse
                ->company_id
        );

        $this->assertSame(
            $companyAId,
            (int) $creator->company_id
        );

        /*
        |--------------------------------------------------------------------------
        | Create Same-Company Transfer
        |--------------------------------------------------------------------------
        */

        $transfer =
            $this
                ->transferService
                ->create(
                    new InventoryTransferCreateDTO(
                        sourceWarehouseId:
                            $this->sourceWarehouse->id,

                        destinationWarehouseId:
                            $this->destinationWarehouse->id,

                        transferDate:
                            '2026-08-27',

                        remarks:
                            'Transfer company ownership test',

                        createdBy:
                            $creator->id,

                        details: [
                            [
                                'item_id' =>
                                    $this->data['item_id'],

                                'qty' =>
                                    20.0,

                                'remarks' =>
                                    'Ownership test',
                            ],
                        ],
                    )
                );

        /*
        |--------------------------------------------------------------------------
        | Assert Ownership
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $companyAId,
            (int) $transfer->company_id
        );

        $this->assertSame(
            (int) $this
                ->sourceWarehouse
                ->company_id,
            (int) $transfer->company_id
        );

        $this->assertSame(
            $creator->id,
            (int) $transfer->created_by
        );
    }

    public function test_inventory_transfer_rejects_destination_from_different_company():
        void
    {
        /*
        |--------------------------------------------------------------------------
        | Company B
        |--------------------------------------------------------------------------
        */

        $companyB =
            Company::create([
                'code' =>
                    'TRF-X-' . substr(
                        uniqid(),
                        -5
                    ),

                'name' =>
                    'Cross Company Transfer B',

                'is_active' =>
                    true,
            ]);

        $branchB =
            Branch::create([
                'company_id' =>
                    $companyB->id,

                'code' =>
                    'TRF-XB-' . substr(
                        uniqid(),
                        -5
                    ),

                'name' =>
                    'Cross Company Transfer Branch B',

                'is_active' =>
                    true,
            ]);

        $warehouseB =
            Warehouse::create([
                'company_id' =>
                    $companyB->id,

                'branch_id' =>
                    $branchB->id,

                'code' =>
                    'WH-TRF-B-' . substr(
                        uniqid(),
                        -5
                    ),

                'name' =>
                    'Cross Company Destination Warehouse',

                'is_active' =>
                    true,
            ]);

        /*
        |--------------------------------------------------------------------------
        | Capture State Before
        |--------------------------------------------------------------------------
        */

        $transferCountBefore =
            InventoryTransfer::query()
                ->count();

        $detailCountBefore =
            InventoryTransferDetail::query()
                ->count();

        $ledgerCountBefore =
            StockLedger::query()
                ->count();

        $sourceBefore =
            $this->state(
                $this->sourceWarehouse->id
            );

        $destinationBefore =
            $this->state(
                $warehouseB->id
            );

        /*
        |--------------------------------------------------------------------------
        | Attempt Cross-Company Transfer
        |--------------------------------------------------------------------------
        */

        try {

            $this->transferService
                ->create(
                    new InventoryTransferCreateDTO(
                        sourceWarehouseId:
                            $this->sourceWarehouse->id,

                        destinationWarehouseId:
                            $warehouseB->id,

                        transferDate:
                            '2026-08-27',

                        remarks:
                            'Cross company transfer must fail',

                        createdBy:
                            $this->data['user_id'],

                        details: [
                            [
                                'item_id' =>
                                    $this->data['item_id'],

                                'qty' =>
                                    20.0,

                                'remarks' =>
                                    'Must never be created',
                            ],
                        ],
                    )
                );

            $this->fail(
                'Expected cross-company inventory transfer exception was not thrown.'
            );

        } catch (RuntimeException $exception) {

            $this->assertSame(
                'Inventory transfer warehouses must belong to the same company.',
                $exception->getMessage()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | No Mutation
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $transferCountBefore,
            InventoryTransfer::query()
                ->count()
        );

        $this->assertSame(
            $detailCountBefore,
            InventoryTransferDetail::query()
                ->count()
        );

        $this->assertSame(
            $ledgerCountBefore,
            StockLedger::query()
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Stock State Must Remain Unchanged
        |--------------------------------------------------------------------------
        */

        $sourceAfter =
            $this->state(
                $this->sourceWarehouse->id
            );

        $destinationAfter =
            $this->state(
                $warehouseB->id
            );

        $this->assertEquals(
            (float) $sourceBefore['qty'],
            (float) $sourceAfter['qty']
        );

        $this->assertEquals(
            (float) $sourceBefore['value'],
            (float) $sourceAfter['value']
        );

        $this->assertEquals(
            (float) $destinationBefore['qty'],
            (float) $destinationAfter['qty']
        );

        $this->assertEquals(
            (float) $destinationBefore['value'],
            (float) $destinationAfter['value']
        );
    }

    public function test_post_rejects_cross_company_transfer_even_if_draft_was_tampered():
        void
    {
        /*
        |--------------------------------------------------------------------------
        | Create Valid Same-Company Draft First
        |--------------------------------------------------------------------------
        */

        $transfer =
            $this->createTransfer(
                20.0
            );

        /*
        |--------------------------------------------------------------------------
        | Create Company B + Warehouse B
        |--------------------------------------------------------------------------
        */

        $companyB =
            \App\Models\Company::create([
                'code' =>
                    'TRF-POST-B-' . substr(
                        uniqid(),
                        -5
                    ),

                'name' =>
                    'Transfer Post Company B',

                'is_active' =>
                    true,
            ]);

        $branchB =
            \App\Models\Branch::create([
                'company_id' =>
                    $companyB->id,

                'code' =>
                    'TRF-POST-BR-' . substr(
                        uniqid(),
                        -5
                    ),

                'name' =>
                    'Transfer Post Branch B',

                'is_active' =>
                    true,
            ]);

        $warehouseB =
            Warehouse::create([
                'company_id' =>
                    $companyB->id,

                'branch_id' =>
                    $branchB->id,

                'code' =>
                    'WH-TRF-POST-B-' . substr(
                        uniqid(),
                        -5
                    ),

                'name' =>
                    'Tampered Cross Company Destination',

                'is_active' =>
                    true,
            ]);

        /*
        |--------------------------------------------------------------------------
        | Simulate Legacy / Tampered Draft
        |--------------------------------------------------------------------------
        */

        $transfer->update([
            'destination_warehouse_id' =>
                $warehouseB->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Capture State Before POST
        |--------------------------------------------------------------------------
        */

        $ledgerCountBefore =
            StockLedger::query()
                ->count();

        $sourceBefore =
            $this->state(
                $this->sourceWarehouse->id
            );

        $destinationBefore =
            $this->state(
                $warehouseB->id
            );

        /*
        |--------------------------------------------------------------------------
        | POST Must Reject Cross-Company Transfer
        |--------------------------------------------------------------------------
        */

        try {

            $this->transferService
                ->post(
                    $transfer->id,
                    $this->data['user_id']
                );

            $this->fail(
                'Expected cross-company inventory transfer posting exception was not thrown.'
            );

        } catch (RuntimeException $exception) {

            $this->assertSame(
                'Inventory transfer warehouses must belong to the same company.',
                $exception->getMessage()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | No Ledger Mutation
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $ledgerCountBefore,
            StockLedger::query()
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Stock Must Remain Unchanged
        |--------------------------------------------------------------------------
        */

        $sourceAfter =
            $this->state(
                $this->sourceWarehouse->id
            );

        $destinationAfter =
            $this->state(
                $warehouseB->id
            );

        $this->assertEquals(
            (float) $sourceBefore['qty'],
            (float) $sourceAfter['qty']
        );

        $this->assertEquals(
            (float) $sourceBefore['value'],
            (float) $sourceAfter['value']
        );

        $this->assertEquals(
            (float) $destinationBefore['qty'],
            (float) $destinationAfter['qty']
        );

        $this->assertEquals(
            (float) $destinationBefore['value'],
            (float) $destinationAfter['value']
        );

        /*
        |--------------------------------------------------------------------------
        | Transfer Must Stay DRAFT
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseHas(
            'inventory_transfers',
            [
                'id' =>
                    $transfer->id,

                'status' =>
                    'DRAFT',
            ]
        );
    }
}
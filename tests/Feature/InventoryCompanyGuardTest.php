<?php

namespace Tests\Feature;

use App\DTO\InventoryAdjustmentCreateDTO;
use App\DTO\InventoryTransferCreateDTO;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\InventoryAdjustment;
use App\Models\InventoryTransfer;
use App\Models\Journal;
use App\Models\StockLedger;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryAdjustmentService;
use App\Services\InventoryTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class InventoryCompanyGuardTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    private Warehouse $sourceWarehouse;

    private Warehouse $destinationWarehouse;

    private User $foreignUser;

    protected function setUp(): void
    {
        parent::setUp();

        /*
        |--------------------------------------------------------------------------
        | Company A Fixture
        |--------------------------------------------------------------------------
        */

        $this->data =
            InventoryReconciliationTestData::create();

        $this->sourceWarehouse =
            Warehouse::findOrFail(
                $this->data['warehouse_id']
            );

        /*
        |--------------------------------------------------------------------------
        | Destination Warehouse - Company A
        |--------------------------------------------------------------------------
        */

        $this->destinationWarehouse =
            Warehouse::create([
                'company_id' =>
                    $this->data['company_id'],

                'branch_id' =>
                    $this->data['branch_id'],

                'code' =>
                    'WH-GUARD-DEST',

                'name' =>
                    'Inventory Guard Destination',

                'is_active' =>
                    true,
            ]);

        /*
        |--------------------------------------------------------------------------
        | Company B + Foreign Actor
        |--------------------------------------------------------------------------
        */

        $foreignCompany =
            Company::create([
                'code' =>
                    'COMP-B',

                'name' =>
                    'Company B',

                'is_active' =>
                    true,
            ]);

        $this->foreignUser =
            User::create([
                'company_id' =>
                    $foreignCompany->id,

                'branch_id' =>
                    null,

                'name' =>
                    'Foreign Inventory Actor',

                'email' =>
                    'foreign-inventory@example.test',

                'password' =>
                    'password',

                'is_active' =>
                    true,
            ]);

        /*
        |--------------------------------------------------------------------------
        | Document Sequences
        |--------------------------------------------------------------------------
        */

        DocumentSequence::firstOrCreate(
            [
                'company_id' =>
                    $this->data['company_id'],
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
    }

    /*
    |--------------------------------------------------------------------------
    | Adjustment Helper
    |--------------------------------------------------------------------------
    */

    private function createAdjustment(
        int $createdBy
    ): InventoryAdjustment {

        return app(
            InventoryAdjustmentService::class
        )->create(
            new InventoryAdjustmentCreateDTO(
                warehouseId:
                    $this->sourceWarehouse->id,

                adjustmentDate:
                    '2026-08-23',

                reason:
                    'STOCK_OPNAME',

                remarks:
                    'Company guard test',

                createdBy:
                    $createdBy,

                details: [
                    [
                        'item_id' =>
                            $this->data['item_id'],

                        'physical_qty' =>
                            170.0,

                        'remarks' =>
                            'Company guard adjustment',
                    ],
                ],
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Transfer Helper
    |--------------------------------------------------------------------------
    */

    private function createTransfer(
        int $createdBy
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
                    'Company guard transfer',

                createdBy:
                    $createdBy,

                details: [
                    [
                        'item_id' =>
                            $this->data['item_id'],

                        'qty' =>
                            10.0,

                        'remarks' =>
                            'Company guard transfer item',
                    ],
                ],
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Adjustment CREATE Guard
    |--------------------------------------------------------------------------
    */

    public function test_inventory_adjustment_create_rejects_actor_from_different_company():
        void
    {
        $adjustmentCountBefore =
            InventoryAdjustment::count();

        try {

            $this->createAdjustment(
                $this->foreignUser->id
            );

            $this->fail(
                'Expected cross-company actor rejection.'
            );

        } catch (RuntimeException $exception) {

            $this->assertSame(
                'Actor does not belong to transaction company.',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            $adjustmentCountBefore,
            InventoryAdjustment::count()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Adjustment POST Guard
    |--------------------------------------------------------------------------
    */

    public function test_inventory_adjustment_post_rejects_actor_from_different_company():
        void
    {
        $adjustment =
            $this->createAdjustment(
                $this->data['user_id']
            );

        $ledgerCountBefore =
            StockLedger::count();

        $journalCountBefore =
            Journal::count();

        try {

            app(
                InventoryAdjustmentService::class
            )->post(
                $adjustment->id,
                $this->foreignUser->id
            );

            $this->fail(
                'Expected cross-company actor rejection.'
            );

        } catch (RuntimeException $exception) {

            $this->assertSame(
                'Actor does not belong to transaction company.',
                $exception->getMessage()
            );
        }

        $adjustment->refresh();

        $this->assertSame(
            'DRAFT',
            $adjustment->status
        );

        $this->assertNull(
            $adjustment->posted_by
        );

        $this->assertNull(
            $adjustment->posted_at
        );

        $this->assertSame(
            $ledgerCountBefore,
            StockLedger::count()
        );

        $this->assertSame(
            $journalCountBefore,
            Journal::count()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Transfer CREATE Guard
    |--------------------------------------------------------------------------
    */

    public function test_inventory_transfer_create_rejects_actor_from_different_company():
        void
    {
        $transferCountBefore =
            InventoryTransfer::count();

        $ledgerCountBefore =
            StockLedger::count();

        try {

            $this->createTransfer(
                $this->foreignUser->id
            );

            $this->fail(
                'Expected cross-company actor rejection.'
            );

        } catch (RuntimeException $exception) {

            $this->assertSame(
                'Actor does not belong to transaction company.',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            $transferCountBefore,
            InventoryTransfer::count()
        );

        $this->assertSame(
            $ledgerCountBefore,
            StockLedger::count()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Transfer POST Guard
    |--------------------------------------------------------------------------
    */

    public function test_inventory_transfer_post_rejects_actor_from_different_company():
        void
    {
        $transfer =
            $this->createTransfer(
                $this->data['user_id']
            );

        $ledgerCountBefore =
            StockLedger::count();

        $journalCountBefore =
            Journal::count();

        try {

            app(
                InventoryTransferService::class
            )->post(
                $transfer->id,
                $this->foreignUser->id
            );

            $this->fail(
                'Expected cross-company actor rejection.'
            );

        } catch (RuntimeException $exception) {

            $this->assertSame(
                'Actor does not belong to transaction company.',
                $exception->getMessage()
            );
        }

        $transfer->refresh();

        $this->assertSame(
            'DRAFT',
            $transfer->status
        );

        $this->assertNull(
            $transfer->posted_by
        );

        $this->assertNull(
            $transfer->posted_at
        );

        $this->assertSame(
            $ledgerCountBefore,
            StockLedger::count()
        );

        $this->assertSame(
            $journalCountBefore,
            Journal::count()
        );
    }
}
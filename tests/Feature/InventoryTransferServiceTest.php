<?php

namespace Tests\Feature;

use App\DTO\InventoryTransferCreateDTO;
use App\Models\DocumentSequence;
use App\Models\InventoryTransfer;
use App\Models\StockLedger;
use App\Models\Warehouse;
use App\Services\InventoryTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class InventoryTransferServiceTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    private Warehouse $sourceWarehouse;

    private Warehouse $destinationWarehouse;

    private InventoryTransferService $service;

    protected function setUp(): void
    {
        parent::setUp();

        /*
        |--------------------------------------------------------------------------
        | Existing Inventory Fixture
        |--------------------------------------------------------------------------
        |
        | Fixture ini merupakan fixture resmi inventory test ERP KSM.
        |
        | Posisi akhir source warehouse:
        |
        | Qty          = 173
        | Average Cost = 6,000
        | Value        = 1,038,000
        |
        */

        $this->data =
            InventoryReconciliationTestData::create();

        /*
        |--------------------------------------------------------------------------
        | Source Warehouse
        |--------------------------------------------------------------------------
        */

        $this->sourceWarehouse =
            Warehouse::query()
                ->findOrFail(
                    $this->data['warehouse_id']
                );

        /*
        |--------------------------------------------------------------------------
        | Destination Warehouse
        |--------------------------------------------------------------------------
        |
        | Menggunakan company dan branch yang sama dengan fixture sehingga
        | seluruh foreign key valid.
        |
        */

        $this->destinationWarehouse =
            Warehouse::query()
                ->create([
                    'company_id' =>
                        $this->data['company_id'],

                    'branch_id' =>
                        $this->data['branch_id'],

                    'code' =>
                        'WH-TRANSFER-DEST',

                    'name' =>
                        'Transfer Destination Warehouse',

                    'phone' =>
                        null,

                    'email' =>
                        null,

                    'address' =>
                        null,

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
                    'Inventory Transfer Test',

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
        | Service
        |--------------------------------------------------------------------------
        */

        $this->service =
            app(
                InventoryTransferService::class
            );
    }

    /*
    |--------------------------------------------------------------------------
    | DTO Helper
    |--------------------------------------------------------------------------
    */

    private function dto(
        ?int $sourceWarehouseId = null,
        ?int $destinationWarehouseId = null,
        ?array $details = null
    ): InventoryTransferCreateDTO {

        return new InventoryTransferCreateDTO(
            sourceWarehouseId:
                $sourceWarehouseId
                ?? $this->sourceWarehouse->id,

            destinationWarehouseId:
                $destinationWarehouseId
                ?? $this->destinationWarehouse->id,

            transferDate:
                '2026-08-27',

            remarks:
                'Inventory Transfer Test',

            createdBy:
                $this->data['user_id'],

            details:
                $details
                ?? [
                    [
                        'item_id' =>
                            $this->data['item_id'],

                        'qty' =>
                            20,

                        'remarks' =>
                            'Transfer Item Test',
                    ],
                ],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE DRAFT
    |--------------------------------------------------------------------------
    */

    public function test_it_creates_draft_inventory_transfer():
        void
    {
        $transfer =
            $this->service->create(
                $this->dto()
            );

        $this->assertInstanceOf(
            InventoryTransfer::class,
            $transfer
        );

        $this->assertSame(
            'DRAFT',
            $transfer->status
        );

        $this->assertSame(
            $this->sourceWarehouse->id,
            (int) $transfer->source_warehouse_id
        );

        $this->assertSame(
            $this->destinationWarehouse->id,
            (int) $transfer->destination_warehouse_id
        );

        $this->assertSame(
            $this->data['user_id'],
            (int) $transfer->created_by
        );

        $this->assertCount(
            1,
            $transfer->details
        );

        $detail =
            $transfer->details->first();

        $this->assertSame(
            $this->data['item_id'],
            (int) $detail->item_id
        );

        $this->assertEquals(
            20.0000,
            (float) $detail->qty
        );

        /*
        |--------------------------------------------------------------------------
        | Draft Cost Snapshot
        |--------------------------------------------------------------------------
        |
        | Fixture memiliki moving-average akhir 6,000.
        |
        */

        $this->assertEquals(
            6000.00,
            (float) $detail->unit_cost
        );

        $this->assertEquals(
            120000.00,
            (float) $detail->total_cost
        );

        $this->assertStringStartsWith(
            'TRF',
            $transfer->transfer_no
        );

        $this->assertDatabaseHas(
            'inventory_transfers',
            [
                'id' =>
                    $transfer->id,

                'status' =>
                    'DRAFT',

                'source_warehouse_id' =>
                    $this->sourceWarehouse->id,

                'destination_warehouse_id' =>
                    $this->destinationWarehouse->id,
            ]
        );

        $this->assertDatabaseHas(
            'inventory_transfer_details',
            [
                'inventory_transfer_id' =>
                    $transfer->id,

                'item_id' =>
                    $this->data['item_id'],
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SOURCE AND DESTINATION MUST DIFFER
    |--------------------------------------------------------------------------
    */

    public function test_source_and_destination_must_be_different():
        void
    {
        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Source warehouse and destination warehouse must be different.'
        );

        $this->service->create(
            $this->dto(
                destinationWarehouseId:
                    $this->sourceWarehouse->id
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DETAILS REQUIRED
    |--------------------------------------------------------------------------
    */

    public function test_transfer_requires_at_least_one_detail():
        void
    {
        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Inventory transfer must contain at least one item.'
        );

        $this->service->create(
            $this->dto(
                details: []
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | QUANTITY MUST BE POSITIVE
    |--------------------------------------------------------------------------
    */

    public function test_transfer_quantity_must_be_greater_than_zero():
        void
    {
        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Transfer quantity must be greater than zero.'
        );

        $this->service->create(
            $this->dto(
                details: [
                    [
                        'item_id' =>
                            $this->data['item_id'],

                        'qty' =>
                            0,

                        'remarks' =>
                            null,
                    ],
                ]
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | INSUFFICIENT SOURCE STOCK
    |--------------------------------------------------------------------------
    */

    public function test_insufficient_source_stock_is_rejected():
        void
    {
        /*
        |--------------------------------------------------------------------------
        | Fixture Closing Qty = 173
        |--------------------------------------------------------------------------
        */

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Insufficient source warehouse stock.'
        );

        $this->service->create(
            $this->dto(
                details: [
                    [
                        'item_id' =>
                            $this->data['item_id'],

                        'qty' =>
                            174,

                        'remarks' =>
                            null,
                    ],
                ]
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DUPLICATE ITEM
    |--------------------------------------------------------------------------
    */

    public function test_duplicate_item_is_rejected():
        void
    {
        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Duplicate item is not allowed in inventory transfer.'
        );

        $this->service->create(
            $this->dto(
                details: [
                    [
                        'item_id' =>
                            $this->data['item_id'],

                        'qty' =>
                            10,

                        'remarks' =>
                            null,
                    ],

                    [
                        'item_id' =>
                            $this->data['item_id'],

                        'qty' =>
                            5,

                        'remarks' =>
                            null,
                    ],
                ]
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DRAFT MUST NOT MODIFY SOURCE STOCK
    |--------------------------------------------------------------------------
    */

    public function test_draft_does_not_modify_source_stock():
        void
    {
        $beforeCount =
            StockLedger::query()
                ->where(
                    'warehouse_id',
                    $this->sourceWarehouse->id
                )
                ->where(
                    'item_id',
                    $this->data['item_id']
                )
                ->count();

        $beforeQty =
            (float)
            StockLedger::query()
                ->where(
                    'warehouse_id',
                    $this->sourceWarehouse->id
                )
                ->where(
                    'item_id',
                    $this->data['item_id']
                )
                ->orderByDesc(
                    'transaction_date'
                )
                ->orderByDesc(
                    'id'
                )
                ->value(
                    'balance_qty'
                );

        $this->assertEquals(
            173.0000,
            $beforeQty
        );

        $this->service->create(
            $this->dto()
        );

        $afterCount =
            StockLedger::query()
                ->where(
                    'warehouse_id',
                    $this->sourceWarehouse->id
                )
                ->where(
                    'item_id',
                    $this->data['item_id']
                )
                ->count();

        $afterQty =
            (float)
            StockLedger::query()
                ->where(
                    'warehouse_id',
                    $this->sourceWarehouse->id
                )
                ->where(
                    'item_id',
                    $this->data['item_id']
                )
                ->orderByDesc(
                    'transaction_date'
                )
                ->orderByDesc(
                    'id'
                )
                ->value(
                    'balance_qty'
                );

        $this->assertSame(
            $beforeCount,
            $afterCount
        );

        $this->assertEquals(
            173.0000,
            $afterQty
        );

        $this->assertEquals(
            $beforeQty,
            $afterQty
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DRAFT MUST NOT MODIFY DESTINATION STOCK
    |--------------------------------------------------------------------------
    */

    public function test_draft_does_not_modify_destination_stock():
        void
    {
        $beforeCount =
            StockLedger::query()
                ->where(
                    'warehouse_id',
                    $this->destinationWarehouse->id
                )
                ->where(
                    'item_id',
                    $this->data['item_id']
                )
                ->count();

        $this->assertSame(
            0,
            $beforeCount
        );

        $this->service->create(
            $this->dto()
        );

        $afterCount =
            StockLedger::query()
                ->where(
                    'warehouse_id',
                    $this->destinationWarehouse->id
                )
                ->where(
                    'item_id',
                    $this->data['item_id']
                )
                ->count();

        $this->assertSame(
            0,
            $afterCount
        );
    }

        /*
    |--------------------------------------------------------------------------
    | POST - SOURCE STOCK DECREASES
    |--------------------------------------------------------------------------
    */

    public function test_post_decreases_source_stock():
        void
    {
        $transfer =
            $this->service->create(
                $this->dto()
            );

        $posted =
            $this->service->post(
                $transfer->id,
                $this->data['user_id']
            );

        $state =
            app(
                \App\Services\InventoryCostingService::class
            )->getCurrentState(
                $this->sourceWarehouse->id,
                $this->data['item_id']
            );

        $this->assertEquals(
            153.0000,
            (float) $state['qty']
        );

        $this->assertSame(
            'POSTED',
            $posted->status
        );
    }

    /*
    |--------------------------------------------------------------------------
    | POST - DESTINATION STOCK INCREASES
    |--------------------------------------------------------------------------
    */

    public function test_post_increases_destination_stock():
        void
    {
        $transfer =
            $this->service->create(
                $this->dto()
            );

        $this->service->post(
            $transfer->id,
            $this->data['user_id']
        );

        $state =
            app(
                \App\Services\InventoryCostingService::class
            )->getCurrentState(
                $this->destinationWarehouse->id,
                $this->data['item_id']
            );

        $this->assertEquals(
            20.0000,
            (float) $state['qty']
        );

        $this->assertEquals(
            120000.00,
            (float) $state['value']
        );

        $this->assertEquals(
            6000.00,
            (float) $state['average_cost']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | POST - SOURCE AND DESTINATION COST MUST MATCH
    |--------------------------------------------------------------------------
    */

    public function test_source_out_and_destination_in_use_identical_cost():
        void
    {
        $transfer =
            $this->service->create(
                $this->dto()
            );

        $this->service->post(
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

        $this->assertEquals(
            6000.00,
            (float) $sourceLedger->unit_cost
        );

        $this->assertEquals(
            6000.00,
            (float) $destinationLedger->unit_cost
        );

        $this->assertEquals(
            (float) $sourceLedger->unit_cost,
            (float) $destinationLedger->unit_cost
        );

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
    | POST - DETAIL STORES AUTHORITATIVE COST
    |--------------------------------------------------------------------------
    */

    public function test_post_updates_detail_with_authoritative_cost():
        void
    {
        $transfer =
            $this->service->create(
                $this->dto()
            );

        $this->service->post(
            $transfer->id,
            $this->data['user_id']
        );

        $detail =
            \App\Models\InventoryTransferDetail::query()
                ->where(
                    'inventory_transfer_id',
                    $transfer->id
                )
                ->where(
                    'item_id',
                    $this->data['item_id']
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
    }

    /*
    |--------------------------------------------------------------------------
    | POST - STATUS AND POSTING AUDIT
    |--------------------------------------------------------------------------
    */

    public function test_post_marks_transfer_as_posted():
        void
    {
        $transfer =
            $this->service->create(
                $this->dto()
            );

        $posted =
            $this->service->post(
                $transfer->id,
                $this->data['user_id']
            );

        $this->assertSame(
            'POSTED',
            $posted->status
        );

        $this->assertSame(
            $this->data['user_id'],
            (int) $posted->posted_by
        );

        $this->assertNotNull(
            $posted->posted_at
        );

        $this->assertDatabaseHas(
            'inventory_transfers',
            [
                'id' =>
                    $transfer->id,

                'status' =>
                    'POSTED',

                'posted_by' =>
                    $this->data['user_id'],
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | POST - TRANSACTION DATE
    |--------------------------------------------------------------------------
    */

    public function test_post_uses_transfer_date_for_stock_ledgers():
        void
    {
        $transfer =
            $this->service->create(
                $this->dto()
            );

        $this->service->post(
            $transfer->id,
            $this->data['user_id']
        );

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
                ->get();

        $this->assertCount(
            2,
            $ledgers
        );

        foreach ($ledgers as $ledger) {

            $this->assertSame(
                '2026-08-27',
                \Illuminate\Support\Carbon::parse(
                    $ledger->transaction_date
                )->toDateString()
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | POST - IDEMPOTENCY
    |--------------------------------------------------------------------------
    */

    public function test_second_post_is_idempotent():
        void
    {
        $transfer =
            $this->service->create(
                $this->dto()
            );

        $first =
            $this->service->post(
                $transfer->id,
                $this->data['user_id']
            );

        $ledgerCountAfterFirst =
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

        $sourceStateAfterFirst =
            app(
                \App\Services\InventoryCostingService::class
            )->getCurrentState(
                $this->sourceWarehouse->id,
                $this->data['item_id']
            );

        $destinationStateAfterFirst =
            app(
                \App\Services\InventoryCostingService::class
            )->getCurrentState(
                $this->destinationWarehouse->id,
                $this->data['item_id']
            );

        $second =
            $this->service->post(
                $transfer->id,
                $this->data['user_id']
            );

        $ledgerCountAfterSecond =
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

        $sourceStateAfterSecond =
            app(
                \App\Services\InventoryCostingService::class
            )->getCurrentState(
                $this->sourceWarehouse->id,
                $this->data['item_id']
            );

        $destinationStateAfterSecond =
            app(
                \App\Services\InventoryCostingService::class
            )->getCurrentState(
                $this->destinationWarehouse->id,
                $this->data['item_id']
            );

        $this->assertSame(
            'POSTED',
            $first->status
        );

        $this->assertSame(
            'POSTED',
            $second->status
        );

        $this->assertSame(
            2,
            $ledgerCountAfterFirst
        );

        $this->assertSame(
            $ledgerCountAfterFirst,
            $ledgerCountAfterSecond
        );

        $this->assertEquals(
            $sourceStateAfterFirst['qty'],
            $sourceStateAfterSecond['qty']
        );

        $this->assertEquals(
            $destinationStateAfterFirst['qty'],
            $destinationStateAfterSecond['qty']
        );

        $this->assertEquals(
            153.0000,
            (float) $sourceStateAfterSecond['qty']
        );

        $this->assertEquals(
            20.0000,
            (float) $destinationStateAfterSecond['qty']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | POST - RECHECK SOURCE STOCK
    |--------------------------------------------------------------------------
    */

    public function test_post_rechecks_source_stock():
        void
    {
        /*
        |--------------------------------------------------------------------------
        | Draft valid saat stock masih 173.
        |--------------------------------------------------------------------------
        */

        $transfer =
            $this->service->create(
                $this->dto(
                    details: [
                        [
                            'item_id' =>
                                $this->data['item_id'],

                            'qty' =>
                                20,

                            'remarks' =>
                                null,
                        ],
                    ]
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Setelah draft dibuat, stock source berkurang 160.
        |
        | 173 - 160 = 13.
        |
        | Transfer membutuhkan 20, sehingga posting harus ditolak.
        |--------------------------------------------------------------------------
        */

        app(
            \App\Services\InventoryTransactionService::class
        )->post(
            new \App\DTO\InventoryTransactionDTO(
                warehouseId:
                    $this->sourceWarehouse->id,

                itemId:
                    $this->data['item_id'],

                referenceType:
                    'TEST_STOCK_CONSUMPTION',

                referenceId:
                    999999,

                qtyIn:
                    0,

                qtyOut:
                    160,

                unitCost:
                    0,

                remarks:
                    'Consume stock before transfer posting',

                transactionDate:
                    '2026-08-27',
            )
        );

        $this->expectException(
            \RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Insufficient source warehouse stock at posting time.'
        );

        $this->service->post(
            $transfer->id,
            $this->data['user_id']
        );
    }

    public function test_post_rolls_back_entire_transfer_when_destination_posting_fails():
        void
    {
        /*
        |--------------------------------------------------------------------------
        | Create DRAFT menggunakan service normal
        |--------------------------------------------------------------------------
        */

        $transfer =
            $this->service->create(
                $this->dto()
            );

        /*
        |--------------------------------------------------------------------------
        | State sebelum POST
        |--------------------------------------------------------------------------
        */

        $ledgerCountBefore =
            StockLedger::count();

        $sourceBefore =
            app(
                \App\Services\InventoryCostingService::class
            )->getCurrentState(
                $this->sourceWarehouse->id,
                $this->data['item_id']
            );

        $destinationBefore =
            app(
                \App\Services\InventoryCostingService::class
            )->getCurrentState(
                $this->destinationWarehouse->id,
                $this->data['item_id']
            );

        $itemBefore =
            \App\Models\Item::findOrFail(
                $this->data['item_id']
            );

        $averageCostBefore =
            (float) $itemBefore->average_cost;

        $detailBefore =
            $transfer
                ->details()
                ->firstOrFail();

        $detailUnitCostBefore =
            (float) $detailBefore->unit_cost;

        $detailTotalCostBefore =
            (float) $detailBefore->total_cost;

        /*
        |--------------------------------------------------------------------------
        | Real InventoryTransactionService
        |--------------------------------------------------------------------------
        |
        | Kita membutuhkan call pertama benar-benar melakukan SOURCE OUT.
        |
        */

        $realInventoryTransactionService =
            app(
                \App\Services\InventoryTransactionService::class
            );

        /*
        |--------------------------------------------------------------------------
        | Mock InventoryTransactionService
        |--------------------------------------------------------------------------
        |
        | Call #1 = SOURCE OUT -> teruskan ke service asli.
        | Call #2 = DESTINATION IN -> paksa gagal.
        |--------------------------------------------------------------------------
        */

        $callNumber = 0;

        $inventoryTransactionMock =
            \Mockery::mock(
                \App\Services\InventoryTransactionService::class
            );

        $inventoryTransactionMock
            ->shouldReceive('post')
            ->twice()
            ->andReturnUsing(
                function (
                    \App\DTO\InventoryTransactionDTO $dto
                ) use (
                    &$callNumber,
                    $realInventoryTransactionService
                ) {

                    $callNumber++;

                    if ($callNumber === 1) {

                        /*
                        |--------------------------------------------------------------------------
                        | SOURCE OUT benar-benar masuk DB transaction.
                        |--------------------------------------------------------------------------
                        */

                        return
                            $realInventoryTransactionService
                                ->post($dto);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | DESTINATION IN dipaksa gagal.
                    |--------------------------------------------------------------------------
                    */

                    throw new \RuntimeException(
                        'Forced destination inventory posting failure.'
                    );
                }
            );

        $this->app->instance(
            \App\Services\InventoryTransactionService::class,
            $inventoryTransactionMock
        );

        /*
        |--------------------------------------------------------------------------
        | Resolve ulang InventoryTransferService
        |--------------------------------------------------------------------------
        |
        | $this->service membawa InventoryTransactionService asli.
        | Setelah binding mock, service harus di-resolve ulang.
        |--------------------------------------------------------------------------
        */

        $postingService =
            app(
                \App\Services\InventoryTransferService::class
            );

        /*
        |--------------------------------------------------------------------------
        | POST harus gagal
        |--------------------------------------------------------------------------
        */

        try {

            $postingService->post(
                $transfer->id,
                $this->data['user_id']
            );

            $this->fail(
                'Inventory transfer posting should have failed.'
            );

        } catch (\RuntimeException $exception) {

            $this->assertSame(
                'Forced destination inventory posting failure.',
                $exception->getMessage()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Transfer harus tetap DRAFT
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | SOURCE OUT harus ikut ROLLBACK
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

        $this->assertSame(
            $ledgerCountBefore,
            StockLedger::count()
        );

        /*
        |--------------------------------------------------------------------------
        | Source stock harus kembali seperti sebelum POST
        |--------------------------------------------------------------------------
        */

        $sourceAfter =
            app(
                \App\Services\InventoryCostingService::class
            )->getCurrentState(
                $this->sourceWarehouse->id,
                $this->data['item_id']
            );

        $this->assertEquals(
            $sourceBefore['qty'],
            $sourceAfter['qty']
        );

        $this->assertEquals(
            $sourceBefore['value'],
            $sourceAfter['value']
        );

        /*
        |--------------------------------------------------------------------------
        | Destination juga tidak boleh berubah
        |--------------------------------------------------------------------------
        */

        $destinationAfter =
            app(
                \App\Services\InventoryCostingService::class
            )->getCurrentState(
                $this->destinationWarehouse->id,
                $this->data['item_id']
            );

        $this->assertEquals(
            $destinationBefore['qty'],
            $destinationAfter['qty']
        );

        $this->assertEquals(
            $destinationBefore['value'],
            $destinationAfter['value']
        );

        /*
        |--------------------------------------------------------------------------
        | Item Average Cost update dari SOURCE OUT harus rollback
        |--------------------------------------------------------------------------
        */

        $itemAfter =
            \App\Models\Item::findOrFail(
                $this->data['item_id']
            );

        $this->assertEquals(
            $averageCostBefore,
            (float) $itemAfter->average_cost
        );

        /*
        |--------------------------------------------------------------------------
        | Detail actual posting cost juga harus rollback
        |--------------------------------------------------------------------------
        |
        | Dalam implementasi sekarang detail baru di-update setelah destination IN.
        | Tetapi invariant ini tetap penting supaya perubahan implementasi di masa
        | depan tidak menyebabkan detail setengah ter-posting.
        |--------------------------------------------------------------------------
        */

        $detailAfter =
            $transfer
                ->details()
                ->firstOrFail();

        $this->assertEquals(
            $detailUnitCostBefore,
            (float) $detailAfter->unit_cost
        );

        $this->assertEquals(
            $detailTotalCostBefore,
            (float) $detailAfter->total_cost
        );
    }
}
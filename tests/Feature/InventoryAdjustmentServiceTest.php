<?php

namespace Tests\Feature;

use App\DTO\InventoryAdjustmentCreateDTO;
use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentDetail;
use App\Services\InventoryAdjustmentService;
use App\Services\InventoryTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryReconciliationTestData;
use App\Models\Account;
use App\Models\DocumentSequence;
use App\Models\ItemCategory;
use Tests\TestCase;

class InventoryAdjustmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private array $data;
    private int $gainAccountId;
    private int $lossAccountId; 

    
    protected function setUp(): void
    {
        parent::setUp();

        $this->data =
            InventoryReconciliationTestData::create();

        /*
        |--------------------------------------------------------------------------
        | Ambil account group yang SUDAH tersedia di fixture
        |--------------------------------------------------------------------------
        |
        | Untuk test ini kita tidak sedang menguji struktur COA.
        | Kita hanya membutuhkan account valid agar foreign key terpenuhi.
        |
        */

        $existingAccount =
            Account::query()
                ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Adjustment Gain Account
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | Adjustment Loss Account
        |--------------------------------------------------------------------------
        */

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
        | Map adjustment accounts ke category fixture
        |--------------------------------------------------------------------------
        */

        $item =
            \App\Models\Item::findOrFail(
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

        /*
        |--------------------------------------------------------------------------
        | ADJ Document Sequence
        |--------------------------------------------------------------------------
        */

        DocumentSequence::firstOrCreate(
            [
                'document_type' => 'ADJ',
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

    public function test_it_creates_draft_inventory_adjustment():
        void
    {
        $service =
            app(
                InventoryAdjustmentService::class
            );

        $adjustment =
            $service->create(
                new InventoryAdjustmentCreateDTO(
                    warehouseId:
                        $this->data['warehouse_id'],

                    adjustmentDate:
                        '2026-08-23',

                    reason:
                        'STOCK_OPNAME',

                    remarks:
                        'Test adjustment',

                    createdBy:
                        $this->data['user_id'],

                    details: [
                        [
                            'item_id' =>
                                $this->data['item_id'],

                            'physical_qty' =>
                                170.0,

                            'remarks' =>
                                'Selisih stock opname',
                        ],
                    ],
                )
            );

        $this->assertSame(
            'DRAFT',
            $adjustment->status
        );

        $this->assertSame(
            1,
            InventoryAdjustment::count()
        );

        $this->assertSame(
            1,
            InventoryAdjustmentDetail::count()
        );

        $detail =
            $adjustment
                ->details
                ->first();

        $this->assertEquals(
            173.0,
            $detail->system_qty
        );

        $this->assertEquals(
            170.0,
            $detail->physical_qty
        );

        $this->assertEquals(
            -3.0,
            $detail->adjustment_qty
        );

        $this->assertEquals(
            6000.0,
            $detail->unit_cost
        );

        $this->assertEquals(
            18000.0,
            $detail->total_cost
        );
    }

    public function test_physical_quantity_cannot_be_negative():
        void
    {
        $this->expectException(
            \RuntimeException::class
        );

        app(
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
                    null,

                createdBy:
                    $this->data['user_id'],

                details: [
                    [
                        'item_id' =>
                            $this->data['item_id'],

                        'physical_qty' =>
                            -1,
                    ],
                ],
            )
        );
    }

    public function test_adjustment_requires_at_least_one_detail():
        void
    {
        $this->expectException(
            \RuntimeException::class
        );

        app(
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
                    null,

                createdBy:
                    $this->data['user_id'],

                details:
                    [],
            )
        );
    }
    public function test_it_posts_inventory_adjustment_out():
        void
    {
        $service =
            app(
                InventoryAdjustmentService::class
            );

        $adjustment =
            $service->create(
                new InventoryAdjustmentCreateDTO(
                    warehouseId:
                        $this->data[
                            'warehouse_id'
                        ],

                    adjustmentDate:
                        '2026-08-23',

                    reason:
                        'STOCK_OPNAME',

                    remarks:
                        null,

                    createdBy:
                        $this->data[
                            'user_id'
                        ],

                    details: [
                        [
                            'item_id' =>
                                $this->data[
                                    'item_id'
                                ],

                            'physical_qty' =>
                                170.0,
                        ],
                    ],
                )
            );

        $posted =
            $service->post(
                $adjustment->id,
                $this->data['user_id']
            );

        $this->assertSame(
            'POSTED',
            $posted->status
        );

        $detail =
            $posted
                ->details
                ->first();

        $this->assertEquals(
            173.0,
            $detail->system_qty
        );

        $this->assertEquals(
            -3.0,
            $detail->adjustment_qty
        );

        $this->assertEquals(
            6000.0,
            $detail->unit_cost
        );

        $this->assertEquals(
            18000.0,
            $detail->total_cost
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

    public function test_it_posts_inventory_adjustment_in():
        void
    {
        $service =
            app(
                InventoryAdjustmentService::class
            );

        $adjustment =
            $service->create(
                new InventoryAdjustmentCreateDTO(
                    warehouseId:
                        $this->data[
                            'warehouse_id'
                        ],

                    adjustmentDate:
                        '2026-08-23',

                    reason:
                        'STOCK_OPNAME',

                    remarks:
                        null,

                    createdBy:
                        $this->data[
                            'user_id'
                        ],

                    details: [
                        [
                            'item_id' =>
                                $this->data[
                                    'item_id'
                                ],

                            'physical_qty' =>
                                176.0,
                        ],
                    ],
                )
            );

        $posted =
            $service->post(
                $adjustment->id,
                $this->data['user_id']
            );

        $detail =
            $posted
                ->details
                ->first();

        $this->assertEquals(
            3.0,
            $detail->adjustment_qty
        );

        $this->assertEquals(
            6000.0,
            $detail->unit_cost
        );

        $this->assertEquals(
            18000.0,
            $detail->total_cost
        );

        $this->assertDatabaseHas(
            'stock_ledgers',
            [
                'reference_type' =>
                    'INVENTORY_ADJUSTMENT',

                'reference_id' =>
                    $adjustment->id,

                'qty_in' =>
                    3.0000,
            ]
        );
    }

    public function test_second_post_is_idempotent():
        void
    {
        $service =
            app(
                InventoryAdjustmentService::class
            );

        $adjustment =
            $service->create(
                new InventoryAdjustmentCreateDTO(
                    warehouseId:
                        $this->data[
                            'warehouse_id'
                        ],

                    adjustmentDate:
                        '2026-08-23',

                    reason:
                        'STOCK_OPNAME',

                    remarks:
                        null,

                    createdBy:
                        $this->data[
                            'user_id'
                        ],

                    details: [
                        [
                            'item_id' =>
                                $this->data[
                                    'item_id'
                                ],

                            'physical_qty' =>
                                170.0,
                        ],
                    ],
                )
            );

        $service->post(
            $adjustment->id,
            $this->data['user_id']
        );

        $ledgerCount =
            \App\Models\StockLedger::where(
                'reference_type',
                'INVENTORY_ADJUSTMENT'
            )
                ->where(
                    'reference_id',
                    $adjustment->id
                )
                ->count();

        $journalCount =
            \App\Models\Journal::where(
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

        $this->assertSame(
            $ledgerCount,
            \App\Models\StockLedger::where(
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
            $journalCount,
            \App\Models\Journal::where(
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

    public function test_post_recalculates_system_quantity():
        void
    {
        $service =
            app(
                InventoryAdjustmentService::class
            );

        $adjustment =
            $service->create(
                new InventoryAdjustmentCreateDTO(
                    warehouseId:
                        $this->data[
                            'warehouse_id'
                        ],

                    adjustmentDate:
                        '2026-08-23',

                    reason:
                        'STOCK_OPNAME',

                    remarks:
                        null,

                    createdBy:
                        $this->data[
                            'user_id'
                        ],

                    details: [
                        [
                            'item_id' =>
                                $this->data[
                                    'item_id'
                                ],

                            'physical_qty' =>
                                170.0,
                        ],
                    ],
                )
            );

        /*
        | Tambah stok setelah DRAFT dibuat.
        */

        app(
            InventoryTransactionService::class
        )->post(
            new \App\DTO\InventoryTransactionDTO(
                warehouseId:
                    $this->data[
                        'warehouse_id'
                    ],

                itemId:
                    $this->data[
                        'item_id'
                    ],

                referenceType:
                    'TEST_AFTER_DRAFT',

                referenceId:
                    999,

                qtyIn:
                    2.0,

                qtyOut:
                    0.0,

                unitCost:
                    6000.0,

                remarks:
                    'Test stock change after draft',
            )
        );

        $posted =
            $service->post(
                $adjustment->id,
                $this->data['user_id']
            );

        $detail =
            $posted
                ->details
                ->first();

        /*
        | Draft system qty sebelumnya 173.
        | Saat POST sudah berubah menjadi 175.
        |
        | Physical = 170.
        | Adjustment harus -5.
        */

        $this->assertEquals(
            175.0,
            $detail->system_qty
        );

        $this->assertEquals(
            -5.0,
            $detail->adjustment_qty
        );
    }

    public function test_adjustment_out_posts_correct_balanced_journal():
        void
    {
        $service =
            app(
                InventoryAdjustmentService::class
            );

        $adjustment =
            $service->create(
                new InventoryAdjustmentCreateDTO(
                    warehouseId:
                        $this->data['warehouse_id'],

                    adjustmentDate:
                        '2026-08-23',

                    reason:
                        'STOCK_OPNAME',

                    remarks:
                        null,

                    createdBy:
                        $this->data['user_id'],

                    details: [
                        [
                            'item_id' =>
                                $this->data['item_id'],

                            'physical_qty' =>
                                170.0,
                        ],
                    ],
                )
            );

        $service->post(
            $adjustment->id,
            $this->data['user_id']
        );

        $journal =
            \App\Models\Journal::query()
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

        /*
        |--------------------------------------------------------------------------
        | Journal harus balanced
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            (float) $journal->details->sum('debit'),
            (float) $journal->details->sum('credit')
        );

        /*
        |--------------------------------------------------------------------------
        | Adjustment OUT
        |--------------------------------------------------------------------------
        |
        | Dr Beban Selisih Persediaan
        | Cr Persediaan
        |
        */

        $lossLine =
            $journal->details
                ->firstWhere(
                    'account_id',
                    $this->lossAccountId
                );

        $inventoryLine =
            $journal->details
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
            (float) $lossLine->debit
        );

        $this->assertEquals(
            0.0,
            (float) $lossLine->credit
        );

        $this->assertEquals(
            0.0,
            (float) $inventoryLine->debit
        );

        $this->assertEquals(
            18000.0,
            (float) $inventoryLine->credit
        );
    }

    public function test_adjustment_in_posts_correct_balanced_journal():
        void
    {
        $service =
            app(
                InventoryAdjustmentService::class
            );

        $adjustment =
            $service->create(
                new InventoryAdjustmentCreateDTO(
                    warehouseId:
                        $this->data['warehouse_id'],

                    adjustmentDate:
                        '2026-08-23',

                    reason:
                        'STOCK_OPNAME',

                    remarks:
                        null,

                    createdBy:
                        $this->data['user_id'],

                    details: [
                        [
                            'item_id' =>
                                $this->data['item_id'],

                            'physical_qty' =>
                                176.0,
                        ],
                    ],
                )
            );

        $service->post(
            $adjustment->id,
            $this->data['user_id']
        );

        $journal =
            \App\Models\Journal::query()
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

        /*
        |--------------------------------------------------------------------------
        | Journal harus balanced
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            (float) $journal->details->sum('debit'),
            (float) $journal->details->sum('credit')
        );

        /*
        |--------------------------------------------------------------------------
        | Adjustment IN
        |--------------------------------------------------------------------------
        |
        | Dr Persediaan
        | Cr Pendapatan Selisih Persediaan
        |
        */

        $inventoryLine =
            $journal->details
                ->firstWhere(
                    'account_id',
                    $this->data[
                        'inventory_account_id'
                    ]
                );

        $gainLine =
            $journal->details
                ->firstWhere(
                    'account_id',
                    $this->gainAccountId
                );

        $this->assertNotNull(
            $inventoryLine
        );

        $this->assertNotNull(
            $gainLine
        );

        $this->assertEquals(
            18000.0,
            (float) $inventoryLine->debit
        );

        $this->assertEquals(
            0.0,
            (float) $inventoryLine->credit
        );

        $this->assertEquals(
            0.0,
            (float) $gainLine->debit
        );

        $this->assertEquals(
            18000.0,
            (float) $gainLine->credit
        );
    }
    
    public function test_post_rolls_back_entire_adjustment_when_journal_posting_fails():
        void
    {
        /*
        |--------------------------------------------------------------------------
        | Create DRAFT menggunakan service normal
        |--------------------------------------------------------------------------
        */

        $service =
            app(
                InventoryAdjustmentService::class
            );

        $adjustment =
            $service->create(
                new InventoryAdjustmentCreateDTO(
                    warehouseId:
                        $this->data['warehouse_id'],

                    adjustmentDate:
                        '2026-08-23',

                    reason:
                        'STOCK_OPNAME',

                    remarks:
                        'Atomic rollback test',

                    createdBy:
                        $this->data['user_id'],

                    details: [
                        [
                            'item_id' =>
                                $this->data['item_id'],

                            'physical_qty' =>
                                170.0,
                        ],
                    ],
                )
            );

        /*
        |--------------------------------------------------------------------------
        | State sebelum POST
        |--------------------------------------------------------------------------
        */

        $ledgerCountBefore =
            \App\Models\StockLedger::count();

        $journalCountBefore =
            \App\Models\Journal::count();

        $itemBefore =
            \App\Models\Item::findOrFail(
                $this->data['item_id']
            );

        $averageCostBefore =
            (float) $itemBefore->average_cost;

        /*
        |--------------------------------------------------------------------------
        | Paksa JournalPostingService gagal
        |--------------------------------------------------------------------------
        */

        $journalPostingMock =
            \Mockery::mock(
                \App\Services\JournalPostingService::class
            );

        $journalPostingMock
            ->shouldReceive('post')
            ->once()
            ->andThrow(
                new \RuntimeException(
                    'Forced journal posting failure.'
                )
            );

        $this->app->instance(
            \App\Services\JournalPostingService::class,
            $journalPostingMock
        );

        /*
        |--------------------------------------------------------------------------
        | Resolve ulang InventoryAdjustmentService
        |--------------------------------------------------------------------------
        |
        | Penting:
        | service sebelumnya sudah membawa JournalPostingService asli.
        |
        | Maka setelah binding mock, kita resolve ulang.
        |--------------------------------------------------------------------------
        */

        $postingService =
            app(
                InventoryAdjustmentService::class
            );

        /*
        |--------------------------------------------------------------------------
        | POST harus gagal
        |--------------------------------------------------------------------------
        */

        try {

            $postingService->post(
                $adjustment->id,
                $this->data['user_id']
            );

            $this->fail(
                'Inventory adjustment posting should have failed.'
            );

        } catch (\RuntimeException $exception) {

            $this->assertSame(
                'Forced journal posting failure.',
                $exception->getMessage()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Adjustment harus tetap DRAFT
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | Stock Ledger adjustment TIDAK BOLEH tersisa
        |--------------------------------------------------------------------------
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

        $this->assertSame(
            $ledgerCountBefore,
            \App\Models\StockLedger::count()
        );

        /*
        |--------------------------------------------------------------------------
        | Journal adjustment juga tidak boleh ada
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseMissing(
            'journals',
            [
                'reference_type' =>
                    'INVENTORY_ADJUSTMENT',

                'reference_id' =>
                    $adjustment->id,
            ]
        );

        $this->assertSame(
            $journalCountBefore,
            \App\Models\Journal::count()
        );

        /*
        |--------------------------------------------------------------------------
        | Update Item Average Cost juga harus rollback
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
        | Detail authoritative update juga harus rollback
        |--------------------------------------------------------------------------
        */

        $detail =
            $adjustment
                ->details()
                ->firstOrFail();

        $this->assertEquals(
            173.0,
            $detail->system_qty
        );

        $this->assertEquals(
            -3.0,
            $detail->adjustment_qty
        );

        $this->assertEquals(
            6000.0,
            $detail->unit_cost
        );

        $this->assertEquals(
            18000.0,
            $detail->total_cost
        );
    }
}
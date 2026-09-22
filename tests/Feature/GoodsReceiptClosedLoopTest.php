<?php

namespace Tests\Feature;

use App\DTO\GoodsReceiptDTO;
use App\DTO\GoodsReceiptLineDTO;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Services\AutoJournalService;
use App\Services\GoodsReceiptService;
use App\Services\InventoryCostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use RuntimeException;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;
use App\Models\Journal;
use App\Models\StockLedger;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\ItemCategory;

class GoodsReceiptClosedLoopTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    protected function setUp(): void
    {
        parent::setUp();

        $this->data =
            InventoryReconciliationTestData::create();

        $this->createDocumentSequence(
            companyId: $this->data['company_id'],
            documentType: 'GR',
            prefix: 'GR'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Complete Closed Loop
    |--------------------------------------------------------------------------
    */

    public function test_goods_receipt_posts_complete_closed_loop(): void
    {
        [$po, $poDetail] =
            $this->createPurchaseOrder(
                qty: 20,
                unitPrice: 8000
            );

        $receiptDate =
            '2026-08-08';

        $gr =
            app(GoodsReceiptService::class)
                ->create(
                    new GoodsReceiptDTO(
                        purchaseOrderId:
                            $po->id,

                        supplierName:
                            'Supplier Test',

                        remarks:
                            'Closed loop GR test',

                        warehouseId:
                            $this->data['warehouse_id'],

                        createdBy:
                            $this->data['user_id'],

                        lines: [
                            new GoodsReceiptLineDTO(
                                itemId:
                                    $this->data['item_id'],

                                qty:
                                    10,

                                unitPrice:
                                    8000,

                                remarks:
                                    'GR test line',

                                purchaseOrderDetailId:
                                    $poDetail->id,
                            ),
                        ],

                        receiptDate:
                            $receiptDate,
                    )
                );

        /*
        |--------------------------------------------------------------------------
        | Goods Receipt Header
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseHas(
            'goods_receipts',
            [
                'id' =>
                    $gr->id,

                'purchase_order_id' =>
                    $po->id,

                'receipt_date' =>
                    $receiptDate,

                'status' =>
                    'POSTED',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Goods Receipt Detail
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseHas(
            'goods_receipt_details',
            [
                'goods_receipt_id' =>
                    $gr->id,

                'purchase_order_detail_id' =>
                    $poDetail->id,

                'item_id' =>
                    $this->data['item_id'],

                'qty_received' =>
                    10,

                'unit_cost' =>
                    8000,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Purchase Order Received Quantity
        |--------------------------------------------------------------------------
        */

        $poDetail->refresh();

        $this->assertEqualsWithDelta(
            10,
            (float) $poDetail->received_qty,
            0.0001
        );

        /*
        |--------------------------------------------------------------------------
        | Stock Ledger
        |--------------------------------------------------------------------------
        */

        $ledger =
            DB::table('stock_ledgers')
                ->where(
                    'reference_type',
                    'GOODS_RECEIPT'
                )
                ->where(
                    'reference_id',
                    $gr->id
                )
                ->where(
                    'warehouse_id',
                    $this->data['warehouse_id']
                )
                ->where(
                    'item_id',
                    $this->data['item_id']
                )
                ->first();

        $this->assertNotNull(
            $ledger
        );

        $this->assertSame(
            $receiptDate,
            (string) $ledger->transaction_date
        );

        $this->assertEqualsWithDelta(
            10,
            (float) $ledger->qty_in,
            0.0001
        );

        $this->assertEqualsWithDelta(
            0,
            (float) $ledger->qty_out,
            0.0001
        );

        $this->assertEqualsWithDelta(
            183,
            (float) $ledger->balance_qty,
            0.0001
        );

        /*
        |--------------------------------------------------------------------------
        | Transaction Value
        |--------------------------------------------------------------------------
        |
        | 10 x 8,000 = 80,000
        |
        */

        $this->assertEqualsWithDelta(
            80000,
            (float) $ledger->total_cost,
            0.01
        );

        /*
        |--------------------------------------------------------------------------
        | Authoritative Inventory State
        |--------------------------------------------------------------------------
        |
        | Before:
        | Qty   = 173
        | Value = 1,038,000
        |
        | GR:
        | Qty   = 10
        | Cost  = 8,000
        | Value = 80,000
        |
        | After:
        | Qty   = 183
        | Value = 1,118,000
        | Avg   = 6,109.29
        |
        */

        $state =
            app(InventoryCostingService::class)
                ->getCurrentState(
                    warehouseId:
                        $this->data['warehouse_id'],

                    itemId:
                        $this->data['item_id'],

                    asOfDate:
                        $receiptDate,
                );

        $this->assertEqualsWithDelta(
            183,
            (float) $state['qty'],
            0.0001
        );

        $this->assertEqualsWithDelta(
            1118000,
            (float) $state['value'],
            0.01
        );

        $this->assertEqualsWithDelta(
            6109.29,
            (float) $state['average_cost'],
            0.01
        );

        /*
        |--------------------------------------------------------------------------
        | Journal Header
        |--------------------------------------------------------------------------
        */

        $journal =
            DB::table('journals')
                ->where(
                    'reference_type',
                    'GOODS_RECEIPT'
                )
                ->where(
                    'reference_id',
                    $gr->id
                )
                ->first();

        $this->assertNotNull(
            $journal
        );

        $this->assertSame(
            $receiptDate,
            (string) $journal->journal_date
        );

        /*
        |--------------------------------------------------------------------------
        | Inventory Debit
        |--------------------------------------------------------------------------
        */

        $inventoryLine =
            DB::table('journal_details')
                ->join(
                    'accounts',
                    'accounts.id',
                    '=',
                    'journal_details.account_id'
                )
                ->where(
                    'journal_details.journal_id',
                    $journal->id
                )
                ->where(
                    'accounts.id',
                    $this->data['inventory_account_id']
                )
                ->select(
                    'journal_details.*',
                    'accounts.code as account_code'
                )
                ->first();

        $this->assertNotNull(
            $inventoryLine
        );

        $this->assertSame(
            '1201-T',
            $inventoryLine->account_code
        );

        $this->assertEqualsWithDelta(
            80000,
            (float) $inventoryLine->debit,
            0.01
        );

        $this->assertEqualsWithDelta(
            0,
            (float) $inventoryLine->credit,
            0.01
        );

        /*
        |--------------------------------------------------------------------------
        | GRNI Credit
        |--------------------------------------------------------------------------
        */

        $grniLine =
            DB::table('journal_details')
                ->where(
                    'journal_id',
                    $journal->id
                )
                ->where(
                    'account_id',
                    $this->data['grni_account_id']
                )
                ->first();

        $this->assertNotNull(
            $grniLine
        );

        $this->assertEqualsWithDelta(
            0,
            (float) $grniLine->debit,
            0.01
        );

        $this->assertEqualsWithDelta(
            80000,
            (float) $grniLine->credit,
            0.01
        );

        /*
        |--------------------------------------------------------------------------
        | Informational Item Cost
        |--------------------------------------------------------------------------
        */

        $item =
            Item::findOrFail(
                $this->data['item_id']
            );

        $this->assertEqualsWithDelta(
            8000,
            (float) $item->last_purchase_price,
            0.01
        );

        /*
        |--------------------------------------------------------------------------
        | Legacy Global Average Cost Must Not Change
        |--------------------------------------------------------------------------
        */

        $this->assertEqualsWithDelta(
            0,
            (float) $item->average_cost,
            0.01
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Over Receipt Guard
    |--------------------------------------------------------------------------
    */

    public function test_goods_receipt_rejects_qty_exceeding_remaining_purchase_order(): void
    {
        [$po, $poDetail] =
            $this->createPurchaseOrder(
                qty: 5,
                unitPrice: 8000
            );

        $beforeGoodsReceipts =
            DB::table(
                'goods_receipts'
            )->count();

        $beforeDetails =
            DB::table(
                'goods_receipt_details'
            )->count();

        $beforeLedgers =
            DB::table(
                'stock_ledgers'
            )->count();

        $beforeJournals =
            DB::table(
                'journals'
            )->count();

        $itemBefore =
            Item::findOrFail(
                $this->data['item_id']
            );

        $lastPurchasePriceBefore =
            (float)
            $itemBefore->last_purchase_price;

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Goods receipt exceeds remaining purchase order qty.'
        );

        try {

            app(GoodsReceiptService::class)
                ->create(
                    new GoodsReceiptDTO(
                        purchaseOrderId:
                            $po->id,

                        supplierName:
                            'Supplier Test',

                        remarks:
                            'Over receipt test',

                        warehouseId:
                            $this->data['warehouse_id'],

                        createdBy:
                            $this->data['user_id'],

                        lines: [
                            new GoodsReceiptLineDTO(
                                itemId:
                                    $this->data['item_id'],

                                qty:
                                    10,

                                unitPrice:
                                    8000,

                                remarks:
                                    null,

                                purchaseOrderDetailId:
                                    $poDetail->id,
                            ),
                        ],

                        receiptDate:
                            '2026-08-08',
                    )
                );

        } finally {

            /*
            |--------------------------------------------------------------------------
            | Entire Transaction Must Roll Back
            |--------------------------------------------------------------------------
            */

            $this->assertSame(
                $beforeGoodsReceipts,
                DB::table(
                    'goods_receipts'
                )->count()
            );

            $this->assertSame(
                $beforeDetails,
                DB::table(
                    'goods_receipt_details'
                )->count()
            );

            $this->assertSame(
                $beforeLedgers,
                DB::table(
                    'stock_ledgers'
                )->count()
            );

            $this->assertSame(
                $beforeJournals,
                DB::table(
                    'journals'
                )->count()
            );

            /*
            |--------------------------------------------------------------------------
            | PO received_qty Must Stay Unchanged
            |--------------------------------------------------------------------------
            */

            $poDetail->refresh();

            $this->assertEqualsWithDelta(
                0,
                (float) $poDetail->received_qty,
                0.0001
            );

            /*
            |--------------------------------------------------------------------------
            | Last Purchase Price Must Stay Unchanged
            |--------------------------------------------------------------------------
            */

            $itemAfter =
                Item::findOrFail(
                    $this->data['item_id']
                );

            $this->assertEqualsWithDelta(
                $lastPurchasePriceBefore,
                (float)
                $itemAfter->last_purchase_price,
                0.01
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Journal Failure Atomic Rollback
    |--------------------------------------------------------------------------
    */

    public function test_goods_receipt_rolls_back_everything_when_journal_fails(): void
    {
        [$po, $poDetail] =
            $this->createPurchaseOrder(
                qty: 20,
                unitPrice: 8000
            );

        $this->mock(
            AutoJournalService::class,
            function (
                MockInterface $mock
            ) {
                $mock
                    ->shouldReceive(
                        'goodsReceipt'
                    )
                    ->once()
                    ->andThrow(
                        new RuntimeException(
                            'Forced journal failure'
                        )
                    );
            }
        );

        $beforeGoodsReceipts =
            DB::table(
                'goods_receipts'
            )->count();

        $beforeDetails =
            DB::table(
                'goods_receipt_details'
            )->count();

        $beforeLedgers =
            DB::table(
                'stock_ledgers'
            )->count();

        $itemBefore =
            Item::findOrFail(
                $this->data['item_id']
            );

        $lastPurchasePriceBefore =
            (float)
            $itemBefore->last_purchase_price;

        try {

            app(GoodsReceiptService::class)
                ->create(
                    new GoodsReceiptDTO(
                        purchaseOrderId:
                            $po->id,

                        supplierName:
                            'Supplier Rollback',

                        remarks:
                            'Rollback test',

                        warehouseId:
                            $this->data['warehouse_id'],

                        createdBy:
                            $this->data['user_id'],

                        lines: [
                            new GoodsReceiptLineDTO(
                                itemId:
                                    $this->data['item_id'],

                                qty:
                                    10,

                                unitPrice:
                                    8000,

                                remarks:
                                    null,

                                purchaseOrderDetailId:
                                    $poDetail->id,
                            ),
                        ],

                        receiptDate:
                            '2026-08-08',
                    )
                );

            $this->fail(
                'Expected journal failure was not thrown.'
            );

        } catch (
            RuntimeException $exception
        ) {

            $this->assertSame(
                'Forced journal failure',
                $exception->getMessage()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Full Database Rollback
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $beforeGoodsReceipts,
            DB::table(
                'goods_receipts'
            )->count()
        );

        $this->assertSame(
            $beforeDetails,
            DB::table(
                'goods_receipt_details'
            )->count()
        );

        $this->assertSame(
            $beforeLedgers,
            DB::table(
                'stock_ledgers'
            )->count()
        );

        /*
        |--------------------------------------------------------------------------
        | PO received_qty Rollback
        |--------------------------------------------------------------------------
        */

        $poDetail->refresh();

        $this->assertEqualsWithDelta(
            0,
            (float) $poDetail->received_qty,
            0.0001
        );

        /*
        |--------------------------------------------------------------------------
        | Item Last Purchase Price Rollback
        |--------------------------------------------------------------------------
        */

        $itemAfter =
            Item::findOrFail(
                $this->data['item_id']
            );

        $this->assertEqualsWithDelta(
            $lastPurchasePriceBefore,
            (float)
            $itemAfter->last_purchase_price,
            0.01
        );
    }


    public function test_goods_receipt_uses_explicit_purchase_order_detail_when_same_item_exists_twice(): void
    {
        $po =
            PurchaseOrder::create([
                'company_id' =>
                    $this->data['company_id'],
                'po_no' =>
                    'PO-DUP-'
                    .
                    uniqid(),

                'purchase_request_id' =>
                    null,

                'po_date' =>
                    '2026-08-08',

                'supplier_name' =>
                    'Supplier Duplicate Test',

                'remarks' =>
                    'Duplicate item GR test',

                'status' =>
                    'APPROVED',

                'created_by' =>
                    $this->data['user_id'],
            ]);

        $firstDetail =
            PurchaseOrderDetail::create([
                'purchase_order_id' =>
                    $po->id,

                'item_id' =>
                    $this->data['item_id'],

                'qty' =>
                    5,

                'received_qty' =>
                    0,

                'unit_price' =>
                    7000,

                'remarks' =>
                    'First duplicate line',
            ]);

        $secondDetail =
            PurchaseOrderDetail::create([
                'purchase_order_id' =>
                    $po->id,

                'item_id' =>
                    $this->data['item_id'],

                'qty' =>
                    20,

                'received_qty' =>
                    0,

                'unit_price' =>
                    8000,

                'remarks' =>
                    'Second duplicate line',
            ]);

        $gr =
            app(GoodsReceiptService::class)
                ->create(
                    new GoodsReceiptDTO(
                        purchaseOrderId:
                            $po->id,

                        supplierName:
                            'Supplier Duplicate Test',

                        remarks:
                            'GR must target second PO detail',

                        warehouseId:
                            $this->data['warehouse_id'],

                        createdBy:
                            $this->data['user_id'],

                        lines: [
                            new GoodsReceiptLineDTO(
                                itemId:
                                    $this->data['item_id'],

                                qty:
                                    10,

                                unitPrice:
                                    8000,

                                remarks:
                                    'Target second detail',

                                purchaseOrderDetailId:
                                    $secondDetail->id,
                            ),
                        ],

                        receiptDate:
                            '2026-08-08',
                    )
                );

        $firstDetail->refresh();
        $secondDetail->refresh();

        /*
        |--------------------------------------------------------------------------
        | Explicit Detail Targeting
        |--------------------------------------------------------------------------
        */

        $this->assertEqualsWithDelta(
            0,
            (float) $firstDetail->received_qty,
            0.0001
        );

        $this->assertEqualsWithDelta(
            10,
            (float) $secondDetail->received_qty,
            0.0001
        );

        /*
        |--------------------------------------------------------------------------
        | GR Detail Must Point To Second PO Detail
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseHas(
            'goods_receipt_details',
            [
                'goods_receipt_id' =>
                    $gr->id,

                'purchase_order_detail_id' =>
                    $secondDetail->id,

                'item_id' =>
                    $this->data['item_id'],

                'qty_received' =>
                    10,

                'unit_cost' =>
                    8000,
            ]
        );

        $this->assertDatabaseMissing(
            'goods_receipt_details',
            [
                'goods_receipt_id' =>
                    $gr->id,

                'purchase_order_detail_id' =>
                    $firstDetail->id,
            ]
        );
    }

    public function test_goods_receipt_rejects_item_mismatch_with_purchase_order_detail(): void
    {
        [$po, $poDetail] =
            $this->createPurchaseOrder(
                qty: 20,
                unitPrice: 8000
            );

        $otherItem =
            $this->createSecondItem();

        $beforeGoodsReceipts =
            DB::table('goods_receipts')
                ->count();

        $beforeDetails =
            DB::table('goods_receipt_details')
                ->count();

        $beforeLedgers =
            DB::table('stock_ledgers')
                ->count();

        $beforeJournals =
            DB::table('journals')
                ->count();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Goods receipt item does not match purchase order detail.'
        );

        try {

            app(GoodsReceiptService::class)
                ->create(
                    new GoodsReceiptDTO(
                        purchaseOrderId:
                            $po->id,

                        supplierName:
                            'Supplier Test',

                        remarks:
                            'Mismatch detail test',

                        warehouseId:
                            $this->data['warehouse_id'],

                        createdBy:
                            $this->data['user_id'],

                        lines: [
                            new GoodsReceiptLineDTO(
                                itemId:
                                    $otherItem->id,

                                qty:
                                    1,

                                unitPrice:
                                    8000,

                                remarks:
                                    null,

                                purchaseOrderDetailId:
                                    $poDetail->id,
                            ),
                        ],

                        receiptDate:
                            '2026-08-08',
                    )
                );

        } finally {

            $this->assertSame(
                $beforeGoodsReceipts,
                DB::table('goods_receipts')
                    ->count()
            );

            $this->assertSame(
                $beforeDetails,
                DB::table('goods_receipt_details')
                    ->count()
            );

            $this->assertSame(
                $beforeLedgers,
                DB::table('stock_ledgers')
                    ->count()
            );

            $this->assertSame(
                $beforeJournals,
                DB::table('journals')
                    ->count()
            );

            $poDetail->refresh();

            $this->assertEqualsWithDelta(
                0,
                (float) $poDetail->received_qty,
                0.0001
            );
        }
    }

    public function test_goods_receipt_updates_purchase_order_status_from_partial_to_completed(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Arrange
        |--------------------------------------------------------------------------
        |
        | PO Qty = 20
        |
        | GR #1 = 10  → PO harus PARTIAL
        | GR #2 = 10  → PO harus COMPLETED
        |
        */

        $item =
            Item::query()
                ->findOrFail(
                    $this->data['item_id']
                );

        [
            $purchaseOrder,
            $purchaseOrderDetail,
        ] =
            $this->createPurchaseOrder(
                qty: 20,
                unitPrice: 8000,
            );

        /*
        |--------------------------------------------------------------------------
        | Initial Status
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            'APPROVED',
            $purchaseOrder->status
        );

        /*
        |--------------------------------------------------------------------------
        | First Goods Receipt — Partial
        |--------------------------------------------------------------------------
        */

        app(GoodsReceiptService::class)
            ->create(
                new GoodsReceiptDTO(
                    purchaseOrderId:
                        $purchaseOrder->id,

                    supplierName:
                        $purchaseOrder->supplier_name,

                    remarks:
                        'Partial receipt test',

                    warehouseId:
                        $this->data['warehouse_id'],

                    createdBy:
                        $this->data['user_id'],

                    lines: [
                        new GoodsReceiptLineDTO(
                            itemId:
                                $item->id,

                            qty:
                                10,

                            unitPrice:
                                8000,

                            purchaseOrderDetailId:
                                $purchaseOrderDetail->id,

                            remarks:
                                'First partial receipt',
                        ),
                    ],

                    receiptDate:
                        '2026-08-08',
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Assert Partial
        |--------------------------------------------------------------------------
        */

        $purchaseOrder->refresh();
        $purchaseOrderDetail->refresh();

        $this->assertEquals(
            10.0,
            (float) $purchaseOrderDetail->received_qty
        );

        $this->assertSame(
            'PARTIAL',
            $purchaseOrder->status
        );

        /*
        |--------------------------------------------------------------------------
        | Second Goods Receipt — Complete Remaining Qty
        |--------------------------------------------------------------------------
        */

        app(GoodsReceiptService::class)
            ->create(
                new GoodsReceiptDTO(
                    purchaseOrderId:
                        $purchaseOrder->id,

                    supplierName:
                        $purchaseOrder->supplier_name,

                    remarks:
                        'Final receipt test',

                    warehouseId:
                        $this->data['warehouse_id'],

                    createdBy:
                        $this->data['user_id'],

                    lines: [
                        new GoodsReceiptLineDTO(
                            itemId:
                                $item->id,

                            qty:
                                10,

                            unitPrice:
                                8000,

                            purchaseOrderDetailId:
                                $purchaseOrderDetail->id,

                            remarks:
                                'Final receipt',
                        ),
                    ],

                    receiptDate:
                        '2026-08-09',
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Assert Completed
        |--------------------------------------------------------------------------
        */

        $purchaseOrder->refresh();
        $purchaseOrderDetail->refresh();

        $this->assertEquals(
            20.0,
            (float) $purchaseOrderDetail->received_qty
        );

        $this->assertSame(
            'COMPLETED',
            $purchaseOrder->status
        );
    }

    public function test_multiline_goods_receipt_creates_one_journal_header(): void
    {
        $item =
            Item::query()
                ->findOrFail(
                    $this->data['item_id']
                );

        /*
        |--------------------------------------------------------------------------
        | Second Item
        |--------------------------------------------------------------------------
        */

        $secondItem =
            $item->replicate();

        $secondItem->code =
            'ITEM-GR-MULTI-' . uniqid();

        $secondItem->name =
            'Second GR Multiline Item';

        $secondItem->average_cost =
            0;

        $secondItem->last_purchase_price =
            0;

        $secondItem->save();

        /*
        |--------------------------------------------------------------------------
        | Purchase Order
        |--------------------------------------------------------------------------
        */

        [
            $purchaseOrder,
            $firstPoDetail,
        ] =
            $this->createPurchaseOrder(
                qty: 10,
                unitPrice: 8000,
            );

        $secondPoDetail =
            $purchaseOrder
                ->details()
                ->create([
                    'item_id' =>
                        $secondItem->id,

                    'qty' =>
                        5,

                    'received_qty' =>
                        0,

                    'unit_price' =>
                        4000,

                    'remarks' =>
                        'Second multiline item',
                ]);

        /*
        |--------------------------------------------------------------------------
        | Capture Before
        |--------------------------------------------------------------------------
        */

        $journalCountBefore =
            Journal::query()
                ->where(
                    'reference_type',
                    'GOODS_RECEIPT'
                )
                ->count();

        $ledgerCountBefore =
            StockLedger::query()
                ->count();

        /*
        |--------------------------------------------------------------------------
        | Act
        |--------------------------------------------------------------------------
        */

        $goodsReceipt =
            app(GoodsReceiptService::class)
                ->create(
                    new GoodsReceiptDTO(
                        purchaseOrderId:
                            $purchaseOrder->id,

                        supplierName:
                            $purchaseOrder->supplier_name,

                        remarks:
                            'Multiline GR journal test',

                        warehouseId:
                            $this->data['warehouse_id'],

                        createdBy:
                            $this->data['user_id'],

                        lines: [
                            new GoodsReceiptLineDTO(
                                itemId:
                                    $item->id,

                                qty:
                                    10,

                                unitPrice:
                                    8000,

                                purchaseOrderDetailId:
                                    $firstPoDetail->id,

                                remarks:
                                    'First item',
                            ),

                            new GoodsReceiptLineDTO(
                                itemId:
                                    $secondItem->id,

                                qty:
                                    5,

                                unitPrice:
                                    4000,

                                purchaseOrderDetailId:
                                    $secondPoDetail->id,

                                remarks:
                                    'Second item',
                            ),
                        ],

                        receiptDate:
                            '2026-08-08',
                    )
                );

        /*
        |--------------------------------------------------------------------------
        | Two Inventory Transactions
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $ledgerCountBefore + 2,
            StockLedger::query()->count()
        );

        $this->assertSame(
            2,
            StockLedger::query()
                ->where(
                    'reference_type',
                    'GOODS_RECEIPT'
                )
                ->where(
                    'reference_id',
                    $goodsReceipt->id
                )
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | ONE Journal Header
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $journalCountBefore + 1,
            Journal::query()
                ->where(
                    'reference_type',
                    'GOODS_RECEIPT'
                )
                ->count()
        );

        $journals =
            Journal::query()
                ->where(
                    'reference_type',
                    'GOODS_RECEIPT'
                )
                ->where(
                    'reference_id',
                    $goodsReceipt->id
                )
                ->get();

        $this->assertCount(
            1,
            $journals
        );

        /*
        |--------------------------------------------------------------------------
        | Journal Balance
        |--------------------------------------------------------------------------
        |
        | Item A = 10 × 8,000 = 80,000
        | Item B =  5 × 4,000 = 20,000
        |
        | Total = 100,000
        |--------------------------------------------------------------------------
        */

        $journal =
            $journals->first();

        $journal->load(
            'details.account'
        );

        $this->assertEquals(
            100000.0,
            (float) $journal->details->sum('debit')
        );

        $this->assertEquals(
            100000.0,
            (float) $journal->details->sum('credit')
        );

        /*
        |--------------------------------------------------------------------------
        | Traceability
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            '2026-08-08',
            $journal->journal_date
        );

        $this->assertSame(
            $goodsReceipt->id,
            (int) $journal->reference_id
        );

        /*
        |--------------------------------------------------------------------------
        | Purchase Order Completed
        |--------------------------------------------------------------------------
        */

        $purchaseOrder->refresh();

        $this->assertSame(
            'COMPLETED',
            $purchaseOrder->status
        );
    }

    public function test_goods_receipt_uses_company_grni_account_mapping(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Arrange — Alternative GRNI Account
        |--------------------------------------------------------------------------
        |
        | Fixture default:
        |
        | GRNI = 2101
        |
        | Untuk membuktikan bahwa accounting mapping authoritative,
        | mapping company diarahkan ke akun alternatif 2998-T.
        |
        */

        $liabilityGroupId =
            DB::table('account_groups')
                ->where(
                    'company_id',
                    $this->data['company_id']
                )
                ->where(
                    'code',
                    'LIA-T'
                )
                ->value('id');

        $this->assertNotNull(
            $liabilityGroupId
        );

        $alternativeGrniAccountId =
            DB::table('accounts')
                ->insertGetId([
                    'company_id' =>
                        $this->data['company_id'],

                    'account_group_id' =>
                        $liabilityGroupId,

                    'code' =>
                        '2998-T',

                    'name' =>
                        'Alternative GRNI Test',

                    'normal_balance' =>
                        'CREDIT',

                    'is_header' =>
                        false,

                    'is_active' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | Change Company Accounting Mapping
        |--------------------------------------------------------------------------
        */

        DB::table(
            'accounting_account_mappings'
        )
            ->where(
                'company_id',
                $this->data['company_id']
            )
            ->update([
                'grni_account_id' =>
                    $alternativeGrniAccountId,

                'updated_at' =>
                    now(),
            ]);

        /*
        |--------------------------------------------------------------------------
        | Purchase Order
        |--------------------------------------------------------------------------
        */

        [
            $purchaseOrder,
            $purchaseOrderDetail,
        ] =
            $this->createPurchaseOrder(
                qty: 10,
                unitPrice: 8000,
            );

        /*
        |--------------------------------------------------------------------------
        | Act
        |--------------------------------------------------------------------------
        */

        $goodsReceipt =
            app(GoodsReceiptService::class)
                ->create(
                    new GoodsReceiptDTO(
                        purchaseOrderId:
                            $purchaseOrder->id,

                        supplierName:
                            $purchaseOrder->supplier_name,

                        remarks:
                            'Dynamic GRNI mapping test',

                        warehouseId:
                            $this->data['warehouse_id'],

                        createdBy:
                            $this->data['user_id'],

                        lines: [
                            new GoodsReceiptLineDTO(
                                itemId:
                                    $this->data['item_id'],

                                qty:
                                    10,

                                unitPrice:
                                    8000,

                                purchaseOrderDetailId:
                                    $purchaseOrderDetail->id,

                                remarks:
                                    'Dynamic GRNI test',
                            ),
                        ],

                        receiptDate:
                            '2026-08-08',
                    )
                );

        /*
        |--------------------------------------------------------------------------
        | Journal
        |--------------------------------------------------------------------------
        */

        $journal =
            Journal::query()
                ->where(
                    'reference_type',
                    'GOODS_RECEIPT'
                )
                ->where(
                    'reference_id',
                    $goodsReceipt->id
                )
                ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Alternative GRNI Must Receive Credit
        |--------------------------------------------------------------------------
        */

        $alternativeGrniLine =
            DB::table('journal_details')
                ->where(
                    'journal_id',
                    $journal->id
                )
                ->where(
                    'account_id',
                    $alternativeGrniAccountId
                )
                ->first();

        $this->assertNotNull(
            $alternativeGrniLine,
            'Goods Receipt must use the company GRNI account mapping.'
        );

        $this->assertEqualsWithDelta(
            0,
            (float) $alternativeGrniLine->debit,
            0.01
        );

        $this->assertEqualsWithDelta(
            80000,
            (float) $alternativeGrniLine->credit,
            0.01
        );

        /*
        |--------------------------------------------------------------------------
        | Old Hard-Coded GRNI Must Not Be Used
        |--------------------------------------------------------------------------
        */

        $oldGrniLineExists =
            DB::table('journal_details')
                ->where(
                    'journal_id',
                    $journal->id
                )
                ->where(
                    'account_id',
                    $this->data['grni_account_id']
                )
                ->exists();

        $this->assertFalse(
            $oldGrniLineExists,
            'Goods Receipt must not use the old hard-coded GRNI account.'
        );

        /*
        |--------------------------------------------------------------------------
        | Journal Must Stay Balanced
        |--------------------------------------------------------------------------
        */

        $journal->load('details');

        $this->assertEqualsWithDelta(
            80000,
            (float) $journal->details->sum('debit'),
            0.01
        );

        $this->assertEqualsWithDelta(
            80000,
            (float) $journal->details->sum('credit'),
            0.01
        );
    }

    public function test_goods_receipt_rolls_back_when_company_accounting_mapping_is_missing(): void
    {
        [
            $purchaseOrder,
            $purchaseOrderDetail,
        ] =
            $this->createPurchaseOrder(
                qty: 10,
                unitPrice: 8000,
            );

        /*
        |--------------------------------------------------------------------------
        | Remove Accounting Mapping
        |--------------------------------------------------------------------------
        */

        DB::table(
            'accounting_account_mappings'
        )
            ->where(
                'company_id',
                $this->data['company_id']
            )
            ->delete();

        /*
        |--------------------------------------------------------------------------
        | Capture Before
        |--------------------------------------------------------------------------
        */

        $beforeGoodsReceipts =
            DB::table('goods_receipts')
                ->count();

        $beforeDetails =
            DB::table('goods_receipt_details')
                ->count();

        $beforeLedgers =
            DB::table('stock_ledgers')
                ->count();

        $beforeJournals =
            DB::table('journals')
                ->count();

        $itemBefore =
            Item::query()
                ->findOrFail(
                    $this->data['item_id']
                );

        $lastPurchasePriceBefore =
            (float)
            $itemBefore->last_purchase_price;

        /*
        |--------------------------------------------------------------------------
        | Act
        |--------------------------------------------------------------------------
        */

        try {

            app(GoodsReceiptService::class)
                ->create(
                    new GoodsReceiptDTO(
                        purchaseOrderId:
                            $purchaseOrder->id,

                        supplierName:
                            $purchaseOrder->supplier_name,

                        remarks:
                            'Missing accounting mapping rollback test',

                        warehouseId:
                            $this->data['warehouse_id'],

                        createdBy:
                            $this->data['user_id'],

                        lines: [
                            new GoodsReceiptLineDTO(
                                itemId:
                                    $this->data['item_id'],

                                qty:
                                    10,

                                unitPrice:
                                    8000,

                                purchaseOrderDetailId:
                                    $purchaseOrderDetail->id,

                                remarks:
                                    'Missing mapping test',
                            ),
                        ],

                        receiptDate:
                            '2026-08-08',
                    )
                );

            $this->fail(
                'Expected missing accounting mapping exception was not thrown.'
            );

        } catch (\Throwable $exception) {

            /*
            |--------------------------------------------------------------------------
            | Current Resolver Behavior
            |--------------------------------------------------------------------------
            |
            | getMapping() masih memakai firstOrFail().
            |
            | Jadi missing mapping saat ini menghasilkan
            | ModelNotFoundException.
            |
            */

            $this->assertInstanceOf(
                \RuntimeException::class,
                $exception
            );

            $this->assertSame(
                sprintf(
                    'Accounting account mapping is not configured for company %d.',
                    $this->data['company_id']
                ),
                $exception->getMessage()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Full Rollback
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $beforeGoodsReceipts,
            DB::table('goods_receipts')
                ->count()
        );

        $this->assertSame(
            $beforeDetails,
            DB::table('goods_receipt_details')
                ->count()
        );

        $this->assertSame(
            $beforeLedgers,
            DB::table('stock_ledgers')
                ->count()
        );

        $this->assertSame(
            $beforeJournals,
            DB::table('journals')
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Purchase Order Detail Rollback
        |--------------------------------------------------------------------------
        */

        $purchaseOrderDetail->refresh();

        $this->assertEqualsWithDelta(
            0,
            (float)
            $purchaseOrderDetail->received_qty,
            0.0001
        );

        /*
        |--------------------------------------------------------------------------
        | Item Rollback
        |--------------------------------------------------------------------------
        */

        $itemAfter =
            Item::query()
                ->findOrFail(
                    $this->data['item_id']
                );

        $this->assertEqualsWithDelta(
            $lastPurchasePriceBefore,
            (float)
            $itemAfter->last_purchase_price,
            0.01
        );
    }

    public function test_goods_receipt_rejects_inventory_account_from_another_company(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Company A
        |--------------------------------------------------------------------------
        |
        | Purchase Order, warehouse, item, dan category fixture semuanya
        | dimiliki Company A.
        |
        */

        $companyAId =
            (int) $this->data['company_id'];

        $item =
            Item::query()
                ->findOrFail(
                    $this->data['item_id']
                );

        $category =
            DB::table('item_categories')
                ->where(
                    'id',
                    $item->item_category_id
                )
                ->first();

        $this->assertNotNull(
            $category
        );

        $this->assertSame(
            $companyAId,
            (int) $category->company_id
        );

        /*
        |--------------------------------------------------------------------------
        | Create Company B
        |--------------------------------------------------------------------------
        */

        $companyBId =
            DB::table('companies')
                ->insertGetId([
                    'code' =>
                        'COMP-GR-B',

                    'name' =>
                        'Company B GR Attack Test',

                    'is_active' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        $this->assertNotSame(
            $companyAId,
            (int) $companyBId
        );

        /*
        |--------------------------------------------------------------------------
        | Company B Account Group
        |--------------------------------------------------------------------------
        */

        $assetGroupBId =
            DB::table('account_groups')
                ->insertGetId([
                    'company_id' =>
                        $companyBId,

                    'code' =>
                        'AST-GR-B',

                    'name' =>
                        'Asset Company B GR Attack',

                    'is_active' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | Company B Inventory Account
        |--------------------------------------------------------------------------
        */

        $inventoryAccountBId =
            DB::table('accounts')
                ->insertGetId([
                    'company_id' =>
                        $companyBId,

                    'account_group_id' =>
                        $assetGroupBId,

                    'code' =>
                        '1201-GR-B',

                    'name' =>
                        'Inventory Company B GR Attack',

                    'normal_balance' =>
                        'DEBIT',

                    'is_header' =>
                        false,

                    'is_active' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | Attack
        |--------------------------------------------------------------------------
        |
        | Category tetap milik Company A.
        |
        | Tetapi inventory_account_id sengaja diarahkan ke account
        | milik Company B.
        |
        */

        DB::table('item_categories')
            ->where(
                'id',
                $item->item_category_id
            )
            ->update([
                'inventory_account_id' =>
                    $inventoryAccountBId,

                'updated_at' =>
                    now(),
            ]);

        /*
        |--------------------------------------------------------------------------
        | Purchase Order Company A
        |--------------------------------------------------------------------------
        */

        [
            $purchaseOrder,
            $purchaseOrderDetail,
        ] =
            $this->createPurchaseOrder(
                qty: 10,
                unitPrice: 8000,
            );

        /*
        |--------------------------------------------------------------------------
        | Capture State Before Attack
        |--------------------------------------------------------------------------
        */

        $goodsReceiptCountBefore =
            DB::table('goods_receipts')
                ->count();

        $goodsReceiptDetailCountBefore =
            DB::table('goods_receipt_details')
                ->count();

        $stockLedgerCountBefore =
            DB::table('stock_ledgers')
                ->count();

        $journalCountBefore =
            DB::table('journals')
                ->count();

        $purchaseOrderDetail->refresh();

        $receivedQtyBefore =
            (float)
            $purchaseOrderDetail->received_qty;

        $item->refresh();

        $lastPurchasePriceBefore =
            (float)
            $item->last_purchase_price;

        /*
        |--------------------------------------------------------------------------
        | Execute Attack
        |--------------------------------------------------------------------------
        */

        try {

            app(GoodsReceiptService::class)
                ->create(
                    new GoodsReceiptDTO(
                        purchaseOrderId:
                            $purchaseOrder->id,

                        supplierName:
                            $purchaseOrder->supplier_name,

                        remarks:
                            'Cross-company inventory account attack',

                        warehouseId:
                            $this->data['warehouse_id'],

                        createdBy:
                            $this->data['user_id'],

                        lines: [
                            new GoodsReceiptLineDTO(
                                itemId:
                                    $this->data['item_id'],

                                qty:
                                    10,

                                unitPrice:
                                    8000,

                                purchaseOrderDetailId:
                                    $purchaseOrderDetail->id,

                                remarks:
                                    'Cross-company account attack',
                            ),
                        ],

                        receiptDate:
                            '2026-08-08',
                    )
                );

            $this->fail(
                'Goods Receipt must reject an inventory account from another company.'
            );

        } catch (RuntimeException $exception) {

            $this->assertSame(
                sprintf(
                    'Inventory account does not belong to company %d.',
                    $companyAId
                ),
                $exception->getMessage()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | No Goods Receipt Must Survive
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $goodsReceiptCountBefore,
            DB::table('goods_receipts')
                ->count()
        );

        $this->assertSame(
            $goodsReceiptDetailCountBefore,
            DB::table('goods_receipt_details')
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | No Inventory Movement
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $stockLedgerCountBefore,
            DB::table('stock_ledgers')
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | No Accounting Journal
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $journalCountBefore,
            DB::table('journals')
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Purchase Order Must Remain Unchanged
        |--------------------------------------------------------------------------
        */

        $purchaseOrderDetail->refresh();

        $this->assertEqualsWithDelta(
            $receivedQtyBefore,
            (float)
            $purchaseOrderDetail->received_qty,
            0.0001
        );

        /*
        |--------------------------------------------------------------------------
        | Item Informational Cost Must Remain Unchanged
        |--------------------------------------------------------------------------
        */

        $item->refresh();

        $this->assertEqualsWithDelta(
            $lastPurchasePriceBefore,
            (float)
            $item->last_purchase_price,
            0.01
        );
    }

    public function test_goods_receipt_rejects_item_from_another_company():
        void
    {
        /*
        |--------------------------------------------------------------------------
        | Arrange - Normal Company A Purchase Order
        |--------------------------------------------------------------------------
        */

        [$purchaseOrder, $purchaseOrderDetail] =
            $this->createPurchaseOrder(
                qty: 20,
                unitPrice: 8000
            );

        $itemA =
            Item::query()
                ->findOrFail(
                    $this->data['item_id']
                );

        /*
        |--------------------------------------------------------------------------
        | Arrange - Company B
        |--------------------------------------------------------------------------
        */

        $companyB =
            Company::query()
                ->create([
                    'code' =>
                        'COMP-GR-B',

                    'name' =>
                        'Goods Receipt Company B',

                    'phone' =>
                        null,

                    'email' =>
                        null,

                    'address' =>
                        null,

                    'is_active' =>
                        true,
                ]);

        $categoryB =
            ItemCategory::query()
                ->create([
                    'company_id' =>
                        $companyB->id,

                    'code' =>
                        'CAT-GR-B',

                    'name' =>
                        'Goods Receipt Category B',

                    'description' =>
                        'Cross-company GR item attack',

                    'inventory_account_id' =>
                        null,

                    'cogs_account_id' =>
                        null,

                    'sales_account_id' =>
                        null,

                    'adjustment_gain_account_id' =>
                        null,

                    'adjustment_loss_account_id' =>
                        null,

                    'is_active' =>
                        true,
                ]);

        $itemB =
            Item::query()
                ->create([
                    'company_id' =>
                        $companyB->id,

                    'item_category_id' =>
                        $categoryB->id,

                    'uom_id' =>
                        $itemA->uom_id,

                    'code' =>
                        'ITEM-GR-B',

                    'name' =>
                        'Goods Receipt Item Company B',

                    'description' =>
                        'Must not enter Company A goods receipt',

                    'minimum_stock' =>
                        0,

                    'maximum_stock' =>
                        0,

                    'average_cost' =>
                        0,

                    'last_purchase_price' =>
                        0,

                    'is_active' =>
                        true,
                ]);

        /*
        |--------------------------------------------------------------------------
        | Tamper PO Detail
        |--------------------------------------------------------------------------
        |
        | Purchase Order tetap milik Company A, tetapi detail sengaja diarahkan
        | ke Item milik Company B.
        |
        */

        $purchaseOrderDetail->update([
            'item_id' =>
                $itemB->id,
        ]);

        $purchaseOrderDetail->refresh();

        /*
        |--------------------------------------------------------------------------
        | Snapshot Before Attack
        |--------------------------------------------------------------------------
        */

        $goodsReceiptCountBefore =
            DB::table('goods_receipts')
                ->count();

        $goodsReceiptDetailCountBefore =
            DB::table('goods_receipt_details')
                ->count();

        $stockLedgerCountBefore =
            StockLedger::query()
                ->count();

        $journalCountBefore =
            Journal::query()
                ->count();

        $receivedQtyBefore =
            (float)
            $purchaseOrderDetail->received_qty;

        $sequenceBefore =
            DocumentSequence::query()
                ->where(
                    'company_id',
                    $this->data['company_id']
                )
                ->where(
                    'document_type',
                    'GR'
                )
                ->value(
                    'current_number'
                );

        /*
        |--------------------------------------------------------------------------
        | Act
        |--------------------------------------------------------------------------
        */

        try {
            app(
                GoodsReceiptService::class
            )->create(
                new GoodsReceiptDTO(
                    purchaseOrderId:
                        $purchaseOrder->id,

                    supplierName:
                        $purchaseOrder->supplier_name,

                    remarks:
                        'Cross-company item attack',

                    warehouseId:
                        $this->data['warehouse_id'],

                    createdBy:
                        $this->data['user_id'],

                    lines: [
                        new GoodsReceiptLineDTO(
                            itemId:
                                $itemB->id,

                            qty:
                                10,

                            unitPrice:
                                8000,

                            purchaseOrderDetailId:
                                $purchaseOrderDetail->id,

                            remarks:
                                'Cross-company item attack',
                        ),
                    ],

                    receiptDate:
                        '2026-08-08',
                )
            );

            $this->fail(
                'Goods Receipt must reject an item from another company.'
            );

        } catch (RuntimeException $exception) {

            $this->assertSame(
                'Item does not belong to transaction company.',
                $exception->getMessage()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Assert - Attack Setup
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            (int) $this->data['company_id'],
            (int) $purchaseOrder->company_id
        );

        $this->assertNotSame(
            (int) $purchaseOrder->company_id,
            (int) $itemB->company_id
        );

        $this->assertSame(
            (int) $itemB->id,
            (int) $purchaseOrderDetail->item_id
        );

        /*
        |--------------------------------------------------------------------------
        | Assert - No Goods Receipt Survives
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $goodsReceiptCountBefore,
            DB::table('goods_receipts')
                ->count()
        );

        $this->assertSame(
            $goodsReceiptDetailCountBefore,
            DB::table('goods_receipt_details')
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Assert - No Inventory Movement
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $stockLedgerCountBefore,
            StockLedger::query()
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Assert - No Accounting Journal
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $journalCountBefore,
            Journal::query()
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Assert - PO Detail Unchanged
        |--------------------------------------------------------------------------
        */

        $purchaseOrderDetail->refresh();

        $this->assertEqualsWithDelta(
            $receivedQtyBefore,
            (float) $purchaseOrderDetail->received_qty,
            0.0001
        );

        /*
        |--------------------------------------------------------------------------
        | Assert - Document Sequence Rolled Back
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $sequenceBefore,
            DocumentSequence::query()
                ->where(
                    'company_id',
                    $this->data['company_id']
                )
                ->where(
                    'document_type',
                    'GR'
                )
                ->value(
                    'current_number'
                )
        );

        /*
        |--------------------------------------------------------------------------
        | Assert - No GR Detail For Foreign Item
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseMissing(
            'goods_receipt_details',
            [
                'item_id' =>
                    $itemB->id,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */
    private function createSecondItem(): Item
    {
        $original =
            Item::query()
                ->findOrFail(
                    $this->data['item_id']
                );

        $secondItem =
            $original->replicate();

        $secondItem->code =
            'ITEM-SECOND-'
            .
            uniqid();

        $secondItem->name =
            'Second Item Test';

        $secondItem->average_cost =
            0;

        $secondItem->last_purchase_price =
            0;

        $secondItem->is_active =
            true;

        $secondItem->save();

        return $secondItem;
    }

    private function createPurchaseOrder(
        float $qty,
        float $unitPrice
    ): array {

        $po =
            PurchaseOrder::create([
                'company_id' =>
                $this->data['company_id'],

                'po_no' =>
                    'PO-TEST-'
                    .
                    uniqid(),

                'purchase_request_id' =>
                    null,

                'po_date' =>
                    '2026-08-08',

                'supplier_name' =>
                    'Supplier Test',

                'remarks' =>
                    'GR closed loop test',

                'status' =>
                    'APPROVED',

                'created_by' =>
                    $this->data['user_id'],
            ]);

        $detail =
            PurchaseOrderDetail::create([
                'purchase_order_id' =>
                    $po->id,

                'item_id' =>
                    $this->data['item_id'],

                'qty' =>
                    $qty,

                'received_qty' =>
                    0,

                'unit_price' =>
                    $unitPrice,

                'remarks' =>
                    'PO detail test',
            ]);

        return [
            $po,
            $detail,
        ];
    }

    private function createDocumentSequence(
        int $companyId,
        string $documentType,
        string $prefix
    ): void {
        DB::table(
            'document_sequences'
        )->insert([
            'company_id' =>
                $companyId,

            'document_type' =>
                $documentType,

            'prefix' =>
                $prefix,

            'description' =>
                $documentType . ' Test Sequence',

            'current_number' =>
                0,

            'padding' =>
                5,

            'is_active' =>
                true,

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);
    }

    
}
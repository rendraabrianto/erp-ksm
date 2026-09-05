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
        string $documentType,
        string $prefix
    ): void {

        DB::table(
            'document_sequences'
        )->insert([
            'document_type' =>
                $documentType,

            'prefix' =>
                $prefix,

            'description' =>
                $documentType
                .
                ' Test Sequence',

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
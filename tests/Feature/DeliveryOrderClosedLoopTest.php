<?php

namespace Tests\Feature;

use App\DTO\DeliveryOrderDTO;
use App\DTO\DeliveryOrderLineDTO;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Services\AutoJournalService;
use App\Services\DeliveryOrderService;
use App\Services\InventoryCostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use RuntimeException;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderDetail;
use App\Models\Journal;
use App\Models\StockLedger;
use App\DTO\InventoryTransactionDTO;
use App\Services\InventoryTransactionService;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\ItemCategory;

class DeliveryOrderClosedLoopTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    protected function setUp(): void
    {
        parent::setUp();

        $this->data =
            InventoryReconciliationTestData::create();

        $this->createDocumentSequence(
            documentType: 'DO',
            prefix: 'DO'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Complete Closed Loop
    |--------------------------------------------------------------------------
    */

    public function test_delivery_order_posts_complete_closed_loop(): void
    {
        [$so, $soDetail] =
            $this->createSalesOrder(
                qty: 20,
                unitPrice: 10000
            );

        /*
        |--------------------------------------------------------------------------
        | Poison Legacy Global Average Cost
        |--------------------------------------------------------------------------
        |
        | DO HPP tidak boleh memakai items.average_cost.
        |
        */

        Item::query()
            ->whereKey(
                $this->data['item_id']
            )
            ->update([
                'average_cost' =>
                    99999,
            ]);

        $deliveryDate =
            '2026-08-08';

        $do =
            app(DeliveryOrderService::class)
                ->create(
                    new DeliveryOrderDTO(
                        salesOrderId:
                            $so->id,

                        remarks:
                            'Closed loop DO test',

                        warehouseId:
                            $this->data['warehouse_id'],

                        createdBy:
                            $this->data['user_id'],

                        lines: [
                            new DeliveryOrderLineDTO(
                                itemId:
                                    $this->data['item_id'],

                                qty:
                                    10,

                                remarks:
                                    'DO test line',

                                salesOrderDetailId:
                                    $soDetail->id,
                            ),
                        ],

                        deliveryDate:
                            $deliveryDate,
                    )
                );

        /*
        |--------------------------------------------------------------------------
        | Delivery Order Header
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseHas(
            'delivery_orders',
            [
                'id' =>
                    $do->id,

                'company_id' =>
                    $this->data['company_id'],

                'sales_order_id' =>
                    $so->id,

                'delivery_date' =>
                    $deliveryDate,

                'status' =>
                    'POSTED',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Delivery Order Detail
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseHas(
            'delivery_order_details',
            [
                'delivery_order_id' =>
                    $do->id,

                'sales_order_detail_id' =>
                    $soDetail->id,

                'item_id' =>
                    $this->data['item_id'],

                'qty' =>
                    10,

                'unit_cost' =>
                    6000,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Sales Order Delivered Quantity
        |--------------------------------------------------------------------------
        */

        $soDetail->refresh();

        $this->assertEqualsWithDelta(
            10,
            (float)
            $soDetail->delivered_qty,
            0.0001
        );

        /*
        |--------------------------------------------------------------------------
        | Stock Ledger
        |--------------------------------------------------------------------------
        */

        $ledger =
            DB::table(
                'stock_ledgers'
            )
                ->where(
                    'reference_type',
                    'DELIVERY_ORDER'
                )
                ->where(
                    'reference_id',
                    $do->id
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
            $this->data['company_id'],
            (int) $ledger->company_id
        );

        $this->assertSame(
            $deliveryDate,
            (string)
            $ledger->transaction_date
        );

        $this->assertEqualsWithDelta(
            0,
            (float) $ledger->qty_in,
            0.0001
        );

        $this->assertEqualsWithDelta(
            10,
            (float) $ledger->qty_out,
            0.0001
        );

        $this->assertEqualsWithDelta(
            163,
            (float) $ledger->balance_qty,
            0.0001
        );

        $this->assertEqualsWithDelta(
            6000,
            (float) $ledger->unit_cost,
            0.01
        );

        /*
        |--------------------------------------------------------------------------
        | HPP Transaction Value
        |--------------------------------------------------------------------------
        |
        | 10 x 6,000 = 60,000
        |
        */

        $this->assertEqualsWithDelta(
            60000,
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
        | Avg   = 6,000
        |
        | DO:
        | Qty   = 10
        | HPP   = 60,000
        |
        | After:
        | Qty   = 163
        | Value = 978,000
        | Avg   = 6,000
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
                        $deliveryDate,
                );

        $this->assertEqualsWithDelta(
            163,
            (float) $state['qty'],
            0.0001
        );

        $this->assertEqualsWithDelta(
            978000,
            (float) $state['value'],
            0.01
        );

        $this->assertEqualsWithDelta(
            6000,
            (float)
            $state['average_cost'],
            0.01
        );

        /*
        |--------------------------------------------------------------------------
        | Journal Header
        |--------------------------------------------------------------------------
        */

        $journal =
            DB::table(
                'journals'
            )
                ->where(
                    'reference_type',
                    'DELIVERY_ORDER'
                )
                ->where(
                    'reference_id',
                    $do->id
                )
                ->first();

        $this->assertNotNull(
            $journal
        );

        $this->assertSame(
            $this->data['company_id'],
            (int) $journal->company_id
        );

        $this->assertSame(
            $deliveryDate,
            (string)
            $journal->journal_date
        );

        /*
        |--------------------------------------------------------------------------
        | COGS Debit
        |--------------------------------------------------------------------------
        */

        $cogsLine =
            DB::table(
                'journal_details'
            )
                ->where(
                    'journal_id',
                    $journal->id
                )
                ->where(
                    'account_id',
                    $this->data['cogs_account_id']
                )
                ->first();

        $this->assertNotNull(
            $cogsLine
        );

        $this->assertEqualsWithDelta(
            60000,
            (float) $cogsLine->debit,
            0.01
        );

        $this->assertEqualsWithDelta(
            0,
            (float) $cogsLine->credit,
            0.01
        );

        /*
        |--------------------------------------------------------------------------
        | Inventory Credit
        |--------------------------------------------------------------------------
        */

        $inventoryLine =
            DB::table(
                'journal_details'
            )
                ->where(
                    'journal_id',
                    $journal->id
                )
                ->where(
                    'account_id',
                    $this->data['inventory_account_id']
                )
                ->first();

        $this->assertNotNull(
            $inventoryLine
        );

        $this->assertEqualsWithDelta(
            0,
            (float) $inventoryLine->debit,
            0.01
        );

        $this->assertEqualsWithDelta(
            60000,
            (float) $inventoryLine->credit,
            0.01
        );

        /*
        |--------------------------------------------------------------------------
        | Legacy Global Average Cost Must Stay Poisoned
        |--------------------------------------------------------------------------
        |
        | Ini membuktikan DO tidak memakai atau menulis ulang
        | items.average_cost.
        |
        */

        $item =
            Item::findOrFail(
                $this->data['item_id']
            );

        $this->assertEqualsWithDelta(
            99999,
            (float) $item->average_cost,
            0.01
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Over Delivery Guard
    |--------------------------------------------------------------------------
    */

    public function test_delivery_order_rejects_qty_exceeding_remaining_sales_order(): void
    {
        [$so, $soDetail] =
            $this->createSalesOrder(
                qty: 5,
                unitPrice: 10000
            );

        $beforeDeliveryOrders =
            DB::table(
                'delivery_orders'
            )->count();

        $beforeDetails =
            DB::table(
                'delivery_order_details'
            )->count();

        $beforeLedgers =
            DB::table(
                'stock_ledgers'
            )->count();

        $beforeJournals =
            DB::table(
                'journals'
            )->count();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Delivery exceeds remaining order qty'
        );

        try {

            app(DeliveryOrderService::class)
                ->create(
                    new DeliveryOrderDTO(
                        salesOrderId:
                            $so->id,

                        remarks:
                            'Over delivery test',

                        warehouseId:
                            $this->data['warehouse_id'],

                        createdBy:
                            $this->data['user_id'],

                        lines: [
                            new DeliveryOrderLineDTO(
                                itemId:
                                    $this->data['item_id'],

                                qty:
                                    10,

                                remarks:
                                    null,

                                salesOrderDetailId:
                                    $soDetail->id,
                            ),
                        ],

                        deliveryDate:
                            '2026-08-08',
                    )
                );

        } finally {

            /*
            |--------------------------------------------------------------------------
            | Full Transaction Rollback
            |--------------------------------------------------------------------------
            */

            $this->assertSame(
                $beforeDeliveryOrders,
                DB::table(
                    'delivery_orders'
                )->count()
            );

            $this->assertSame(
                $beforeDetails,
                DB::table(
                    'delivery_order_details'
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
            | delivered_qty Must Stay Unchanged
            |--------------------------------------------------------------------------
            */

            $soDetail->refresh();

            $this->assertEqualsWithDelta(
                0,
                (float)
                $soDetail->delivered_qty,
                0.0001
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Journal Failure Atomic Rollback
    |--------------------------------------------------------------------------
    */

    public function test_delivery_order_rolls_back_everything_when_journal_fails(): void
    {
        [$so, $soDetail] =
            $this->createSalesOrder(
                qty: 20,
                unitPrice: 10000
            );

        $this->mock(
            AutoJournalService::class,
            function (
                MockInterface $mock
            ) {
                $mock
                    ->shouldReceive(
                        'deliveryOrder'
                    )
                    ->once()
                    ->andThrow(
                        new RuntimeException(
                            'Forced journal failure'
                        )
                    );
            }
        );

        $beforeDeliveryOrders =
            DB::table(
                'delivery_orders'
            )->count();

        $beforeDetails =
            DB::table(
                'delivery_order_details'
            )->count();

        $beforeLedgers =
            DB::table(
                'stock_ledgers'
            )->count();

        try {

            app(DeliveryOrderService::class)
                ->create(
                    new DeliveryOrderDTO(
                        salesOrderId:
                            $so->id,

                        remarks:
                            'Rollback DO test',

                        warehouseId:
                            $this->data['warehouse_id'],

                        createdBy:
                            $this->data['user_id'],

                        lines: [
                            new DeliveryOrderLineDTO(
                                itemId:
                                    $this->data['item_id'],

                                qty:
                                    10,

                                remarks:
                                    null,

                                salesOrderDetailId:
                                    $soDetail->id,
                            ),
                        ],

                        deliveryDate:
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
        | Full Rollback
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $beforeDeliveryOrders,
            DB::table(
                'delivery_orders'
            )->count()
        );

        $this->assertSame(
            $beforeDetails,
            DB::table(
                'delivery_order_details'
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
        | delivered_qty Rollback
        |--------------------------------------------------------------------------
        */

        $soDetail->refresh();

        $this->assertEqualsWithDelta(
            0,
            (float)
            $soDetail->delivered_qty,
            0.0001
        );
    }

    public function test_delivery_order_uses_explicit_sales_order_detail_when_same_item_exists_twice(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Create Sales Order Header
        |--------------------------------------------------------------------------
        */

        DB::statement(
            'SET FOREIGN_KEY_CHECKS=0'
        );

        try {

            $so =
                SalesOrder::create([
                    'company_id' =>
                        $this->data['company_id'],

                    'so_no' =>
                        'SO-DUP-'
                        .
                        uniqid(),

                    'customer_id' =>
                        999999,

                    'order_date' =>
                        '2026-08-08',

                    'delivery_date' =>
                        '2026-08-08',

                    'status' =>
                        'APPROVED',

                    'remarks' =>
                        'Duplicate item DO test',

                    'created_by' =>
                        $this->data['user_id'],
                ]);

        } finally {

            DB::statement(
                'SET FOREIGN_KEY_CHECKS=1'
            );
        }

        $firstDetail =
            SalesOrderDetail::create([
                'sales_order_id' =>
                    $so->id,

                'item_id' =>
                    $this->data['item_id'],

                'qty' =>
                    5,

                'unit_price' =>
                    10000,

                'discount' =>
                    0,

                'delivered_qty' =>
                    0,

                'remarks' =>
                    'First duplicate line',
            ]);

        $secondDetail =
            SalesOrderDetail::create([
                'sales_order_id' =>
                    $so->id,

                'item_id' =>
                    $this->data['item_id'],

                'qty' =>
                    20,

                'unit_price' =>
                    11000,

                'discount' =>
                    0,

                'delivered_qty' =>
                    0,

                'remarks' =>
                    'Second duplicate line',
            ]);

        $do =
            app(DeliveryOrderService::class)
                ->create(
                    new DeliveryOrderDTO(
                        salesOrderId:
                            $so->id,

                        remarks:
                            'DO must target second SO detail',

                        warehouseId:
                            $this->data['warehouse_id'],

                        createdBy:
                            $this->data['user_id'],

                        lines: [
                            new DeliveryOrderLineDTO(
                                itemId:
                                    $this->data['item_id'],

                                qty:
                                    10,

                                remarks:
                                    'Target second detail',

                                salesOrderDetailId:
                                    $secondDetail->id,
                            ),
                        ],

                        deliveryDate:
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
            (float) $firstDetail->delivered_qty,
            0.0001
        );

        $this->assertEqualsWithDelta(
            10,
            (float) $secondDetail->delivered_qty,
            0.0001
        );

        /*
        |--------------------------------------------------------------------------
        | DO Detail Must Point To Second SO Detail
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseHas(
            'delivery_order_details',
            [
                'delivery_order_id' =>
                    $do->id,

                'sales_order_detail_id' =>
                    $secondDetail->id,

                'item_id' =>
                    $this->data['item_id'],

                'qty' =>
                    10,

                'unit_cost' =>
                    6000,
            ]
        );

        $this->assertDatabaseMissing(
            'delivery_order_details',
            [
                'delivery_order_id' =>
                    $do->id,

                'sales_order_detail_id' =>
                    $firstDetail->id,
            ]
        );
    }

    public function test_delivery_order_rejects_item_mismatch_with_sales_order_detail(): void
    {
        [$so, $soDetail] =
            $this->createSalesOrder(
                qty: 20,
                unitPrice: 10000
            );

        $otherItem =
            $this->createSecondItem();

        $beforeDeliveryOrders =
            DB::table('delivery_orders')
                ->count();

        $beforeDetails =
            DB::table('delivery_order_details')
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
            'Delivery order item does not match sales order detail.'
        );

        try {

            app(DeliveryOrderService::class)
                ->create(
                    new DeliveryOrderDTO(
                        salesOrderId:
                            $so->id,

                        remarks:
                            'Mismatch detail test',

                        warehouseId:
                            $this->data['warehouse_id'],

                        createdBy:
                            $this->data['user_id'],

                        lines: [
                            new DeliveryOrderLineDTO(
                                itemId:
                                    $otherItem->id,

                                qty:
                                    1,

                                remarks:
                                    null,

                                salesOrderDetailId:
                                    $soDetail->id,
                            ),
                        ],

                        deliveryDate:
                            '2026-08-08',
                    )
                );

        } finally {

            $this->assertSame(
                $beforeDeliveryOrders,
                DB::table('delivery_orders')
                    ->count()
            );

            $this->assertSame(
                $beforeDetails,
                DB::table('delivery_order_details')
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

            $soDetail->refresh();

            $this->assertEqualsWithDelta(
                0,
                (float) $soDetail->delivered_qty,
                0.0001
            );
        }
    }

    public function test_delivery_order_rejects_insufficient_warehouse_stock_and_rolls_back_everything(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Arrange
        |--------------------------------------------------------------------------
        |
        | SO quantity dibuat lebih besar daripada stock warehouse.
        |
        | Dengan demikian:
        |
        | - SO remaining qty masih cukup
        | - kegagalan harus berasal dari InventoryCostingService
        | - seluruh transaksi DO harus rollback
        |
        */

        $item =
            Item::query()
                ->findOrFail(
                    $this->data['item_id']
                );

        [
            $salesOrder,
            $salesOrderDetail,
        ] =
            $this->createSalesOrder(
                qty: 500,
                unitPrice: 10000,
            );

        /*
        |--------------------------------------------------------------------------
        | Capture State Before Transaction
        |--------------------------------------------------------------------------
        */

        $deliveryOrderCountBefore =
            DeliveryOrder::query()
                ->count();

        $deliveryOrderDetailCountBefore =
            DeliveryOrderDetail::query()
                ->count();

        $stockLedgerCountBefore =
            StockLedger::query()
                ->count();

        $journalCountBefore =
            Journal::query()
                ->count();

        /*
        |--------------------------------------------------------------------------
        | Act
        |--------------------------------------------------------------------------
        */

        try {

            app(DeliveryOrderService::class)
                ->create(
                    new DeliveryOrderDTO(
                        salesOrderId:
                            $salesOrder->id,

                        remarks:
                            'Insufficient warehouse stock test',

                        warehouseId:
                            $this->data['warehouse_id'],

                        createdBy:
                            $this->data['user_id'],

                        lines: [
                            new DeliveryOrderLineDTO(
                                itemId:
                                    $item->id,

                                qty:
                                    500,

                                salesOrderDetailId:
                                    $salesOrderDetail->id,

                                remarks:
                                    'Insufficient warehouse stock test',
                            ),
                        ],

                        deliveryDate:
                            '2026-08-08',
                    )
                );

            $this->fail(
                'Expected insufficient stock exception was not thrown.'
            );

        } catch (\Exception $exception) {

            /*
            |--------------------------------------------------------------------------
            | Assert Exception
            |--------------------------------------------------------------------------
            */

            $this->assertSame(
                'Insufficient stock.',
                $exception->getMessage()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Assert Entire Transaction Rolled Back
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $deliveryOrderCountBefore,
            DeliveryOrder::query()->count()
        );

        $this->assertSame(
            $deliveryOrderDetailCountBefore,
            DeliveryOrderDetail::query()->count()
        );

        $this->assertSame(
            $stockLedgerCountBefore,
            StockLedger::query()->count()
        );

        $this->assertSame(
            $journalCountBefore,
            Journal::query()->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Sales Order Must Remain Untouched
        |--------------------------------------------------------------------------
        */

        $salesOrderDetail->refresh();

        $this->assertEquals(
            0.0,
            (float) $salesOrderDetail->delivered_qty
        );

        /*
        |--------------------------------------------------------------------------
        | Failed DO Header Must Not Exist
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseMissing(
            'delivery_orders',
            [
                'sales_order_id' =>
                    $salesOrder->id,

                'remarks' =>
                    'Insufficient warehouse stock test',
            ]
        );
    }

    public function test_delivery_order_updates_sales_order_status_from_partial_to_completed(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Arrange
        |--------------------------------------------------------------------------
        |
        | SO Qty = 20
        |
        | DO #1 = 10  → SO harus PARTIAL
        | DO #2 = 10  → SO harus COMPLETED
        |
        */

        $item =
            Item::query()
                ->findOrFail(
                    $this->data['item_id']
                );

        [
            $salesOrder,
            $salesOrderDetail,
        ] =
            $this->createSalesOrder(
                qty: 20,
                unitPrice: 10000,
            );

        /*
        |--------------------------------------------------------------------------
        | Initial Status
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            'APPROVED',
            $salesOrder->status
        );

        /*
        |--------------------------------------------------------------------------
        | First Delivery Order — Partial
        |--------------------------------------------------------------------------
        */

        app(DeliveryOrderService::class)
            ->create(
                new DeliveryOrderDTO(
                    salesOrderId:
                        $salesOrder->id,

                    remarks:
                        'Partial delivery test',

                    warehouseId:
                        $this->data['warehouse_id'],

                    createdBy:
                        $this->data['user_id'],

                    lines: [
                        new DeliveryOrderLineDTO(
                            itemId:
                                $item->id,

                            qty:
                                10,

                            salesOrderDetailId:
                                $salesOrderDetail->id,

                            remarks:
                                'First partial delivery',
                        ),
                    ],

                    deliveryDate:
                        '2026-08-08',
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Assert Partial
        |--------------------------------------------------------------------------
        */

        $salesOrder->refresh();
        $salesOrderDetail->refresh();

        $this->assertEquals(
            10.0,
            (float) $salesOrderDetail->delivered_qty
        );

        $this->assertSame(
            'PARTIAL',
            $salesOrder->status
        );

        /*
        |--------------------------------------------------------------------------
        | Second Delivery Order — Complete Remaining Qty
        |--------------------------------------------------------------------------
        */

        app(DeliveryOrderService::class)
            ->create(
                new DeliveryOrderDTO(
                    salesOrderId:
                        $salesOrder->id,

                    remarks:
                        'Final delivery test',

                    warehouseId:
                        $this->data['warehouse_id'],

                    createdBy:
                        $this->data['user_id'],

                    lines: [
                        new DeliveryOrderLineDTO(
                            itemId:
                                $item->id,

                            qty:
                                10,

                            salesOrderDetailId:
                                $salesOrderDetail->id,

                            remarks:
                                'Final delivery',
                        ),
                    ],

                    deliveryDate:
                        '2026-08-09',
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Assert Completed
        |--------------------------------------------------------------------------
        */

        $salesOrder->refresh();
        $salesOrderDetail->refresh();

        $this->assertEquals(
            20.0,
            (float) $salesOrderDetail->delivered_qty
        );

        $this->assertSame(
            'COMPLETED',
            $salesOrder->status
        );
    }

    public function test_multiline_delivery_order_creates_one_journal_header(): void
    {
        $firstItem =
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
            $firstItem->replicate();

        $secondItem->code =
            'ITEM-DO-MULTI-' . uniqid();

        $secondItem->name =
            'Second DO Multiline Item';

        $secondItem->average_cost =
            0;

        $secondItem->last_purchase_price =
            0;

        $secondItem->save();

        /*
        |--------------------------------------------------------------------------
        | Seed Stock For Second Item
        |--------------------------------------------------------------------------
        |
        | 20 units @ 4,000
        |
        */

        app(InventoryTransactionService::class)
        ->post(
            new InventoryTransactionDTO(
                warehouseId:
                    $this->data['warehouse_id'],

                itemId:
                    $secondItem->id,

                referenceType:
                    'OPENING',

                referenceId:
                    $secondItem->id,

                qtyIn:
                    20,

                qtyOut:
                    0,

                unitCost:
                    4000,

                remarks:
                    'Opening stock second multiline item',

                transactionDate:
                    '2026-08-07',
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Sales Order
        |--------------------------------------------------------------------------
        */

        [
            $salesOrder,
            $firstSoDetail,
        ] =
            $this->createSalesOrder(
                qty: 10,
                unitPrice: 10000,
            );

        $secondSoDetail =
            $salesOrder
                ->details()
                ->create([
                    'item_id' =>
                        $secondItem->id,

                    'qty' =>
                        5,

                    'unit_price' =>
                        7000,

                    'discount' =>
                        0,

                    'delivered_qty' =>
                        0,

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
                    'DELIVERY_ORDER'
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

        $deliveryOrder =
            app(DeliveryOrderService::class)
                ->create(
                    new DeliveryOrderDTO(
                        salesOrderId:
                            $salesOrder->id,

                        remarks:
                            'Multiline DO journal test',

                        warehouseId:
                            $this->data['warehouse_id'],

                        createdBy:
                            $this->data['user_id'],

                        lines: [
                            new DeliveryOrderLineDTO(
                                itemId:
                                    $firstItem->id,

                                qty:
                                    10,

                                salesOrderDetailId:
                                    $firstSoDetail->id,

                                remarks:
                                    'First item',
                            ),

                            new DeliveryOrderLineDTO(
                                itemId:
                                    $secondItem->id,

                                qty:
                                    5,

                                salesOrderDetailId:
                                    $secondSoDetail->id,

                                remarks:
                                    'Second item',
                            ),
                        ],

                        deliveryDate:
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
                    'DELIVERY_ORDER'
                )
                ->where(
                    'reference_id',
                    $deliveryOrder->id
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
                    'DELIVERY_ORDER'
                )
                ->count()
        );

        $journals =
            Journal::query()
                ->where(
                    'reference_type',
                    'DELIVERY_ORDER'
                )
                ->where(
                    'reference_id',
                    $deliveryOrder->id
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
        | First item:
        | fixture moving average = 6,000
        | 10 × 6,000 = 60,000
        |
        | Second item:
        | opening cost = 4,000
        | 5 × 4,000 = 20,000
        |
        | Total HPP = 80,000
        |--------------------------------------------------------------------------
        */

        $journal =
            $journals->first();

        $journal->load(
            'details.account'
        );

        $this->assertEquals(
            80000.0,
            (float) $journal->details->sum('debit')
        );

        $this->assertEquals(
            80000.0,
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
            $deliveryOrder->id,
            (int) $journal->reference_id
        );

        /*
        |--------------------------------------------------------------------------
        | Sales Order Completed
        |--------------------------------------------------------------------------
        */

        $salesOrder->refresh();

        $this->assertSame(
            'COMPLETED',
            $salesOrder->status
        );
    }

    public function test_delivery_order_rejects_inventory_account_from_another_company(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Company A Context
        |--------------------------------------------------------------------------
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
        | Company B
        |--------------------------------------------------------------------------
        */

        $companyBId =
            DB::table('companies')
                ->insertGetId([
                    'code' =>
                        'COMP-DO-INV-B',

                    'name' =>
                        'Company B DO Inventory Attack',

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
        | Company B Asset Group
        |--------------------------------------------------------------------------
        */

        $assetGroupBId =
            DB::table('account_groups')
                ->insertGetId([
                    'company_id' =>
                        $companyBId,

                    'code' =>
                        'AST-DO-B',

                    'name' =>
                        'Asset Company B DO Attack',

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
                        '1201-DO-B',

                    'name' =>
                        'Inventory Company B DO Attack',

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
        | Poison Category A
        |--------------------------------------------------------------------------
        |
        | Item Category tetap milik Company A.
        | Inventory Account sengaja diarahkan ke Company B.
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
        | Sales Order Company A
        |--------------------------------------------------------------------------
        */

        [
            $salesOrder,
            $salesOrderDetail,
        ] =
            $this->createSalesOrder(
                qty: 10,
                unitPrice: 10000,
            );

        /*
        |--------------------------------------------------------------------------
        | Capture Before Attack
        |--------------------------------------------------------------------------
        */

        $deliveryOrderCountBefore =
            DeliveryOrder::query()
                ->count();

        $deliveryOrderDetailCountBefore =
            DeliveryOrderDetail::query()
                ->count();

        $stockLedgerCountBefore =
            StockLedger::query()
                ->count();

        $journalCountBefore =
            Journal::query()
                ->count();

        $salesOrderStatusBefore =
            $salesOrder->status;

        $salesOrderDetail->refresh();

        $deliveredQtyBefore =
            (float)
            $salesOrderDetail->delivered_qty;

        /*
        |--------------------------------------------------------------------------
        | Execute Attack
        |--------------------------------------------------------------------------
        */

        try {

            app(DeliveryOrderService::class)
                ->create(
                    new DeliveryOrderDTO(
                        salesOrderId:
                            $salesOrder->id,

                        remarks:
                            'Cross-company inventory account attack',

                        warehouseId:
                            $this->data['warehouse_id'],

                        createdBy:
                            $this->data['user_id'],

                        lines: [
                            new DeliveryOrderLineDTO(
                                itemId:
                                    $this->data['item_id'],

                                qty:
                                    10,

                                salesOrderDetailId:
                                    $salesOrderDetail->id,

                                remarks:
                                    'Cross-company inventory attack',
                            ),
                        ],

                        deliveryDate:
                            '2026-08-08',
                    )
                );

            $this->fail(
                'Delivery Order must reject an inventory account from another company.'
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
        | Transaction Must Fully Roll Back
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $deliveryOrderCountBefore,
            DeliveryOrder::query()
                ->count()
        );

        $this->assertSame(
            $deliveryOrderDetailCountBefore,
            DeliveryOrderDetail::query()
                ->count()
        );

        $this->assertSame(
            $stockLedgerCountBefore,
            StockLedger::query()
                ->count()
        );

        $this->assertSame(
            $journalCountBefore,
            Journal::query()
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Sales Order Must Remain Unchanged
        |--------------------------------------------------------------------------
        */

        $salesOrder->refresh();
        $salesOrderDetail->refresh();

        $this->assertSame(
            $salesOrderStatusBefore,
            $salesOrder->status
        );

        $this->assertEqualsWithDelta(
            $deliveredQtyBefore,
            (float)
            $salesOrderDetail->delivered_qty,
            0.0001
        );
    }

    public function test_delivery_order_rejects_cogs_account_from_another_company(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Company A Context
        |--------------------------------------------------------------------------
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
        | Company B
        |--------------------------------------------------------------------------
        */

        $companyBId =
            DB::table('companies')
                ->insertGetId([
                    'code' =>
                        'COMP-DO-COGS-B',

                    'name' =>
                        'Company B DO COGS Attack',

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
        | Company B Expense Group
        |--------------------------------------------------------------------------
        */

        $expenseGroupBId =
            DB::table('account_groups')
                ->insertGetId([
                    'company_id' =>
                        $companyBId,

                    'code' =>
                        'EXP-DO-B',

                    'name' =>
                        'Expense Company B DO Attack',

                    'is_active' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | Company B COGS Account
        |--------------------------------------------------------------------------
        */

        $cogsAccountBId =
            DB::table('accounts')
                ->insertGetId([
                    'company_id' =>
                        $companyBId,

                    'account_group_id' =>
                        $expenseGroupBId,

                    'code' =>
                        '5001-DO-B',

                    'name' =>
                        'COGS Company B DO Attack',

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
        | Poison Category A
        |--------------------------------------------------------------------------
        |
        | Inventory account tetap valid milik Company A.
        | Hanya COGS account yang diarahkan ke Company B.
        |
        | Ini penting supaya eksekusi berhasil melewati inventory-account
        | guard dan benar-benar mencapai COGS-account guard.
        |
        */

        DB::table('item_categories')
            ->where(
                'id',
                $item->item_category_id
            )
            ->update([
                'cogs_account_id' =>
                    $cogsAccountBId,

                'updated_at' =>
                    now(),
            ]);

        /*
        |--------------------------------------------------------------------------
        | Sales Order Company A
        |--------------------------------------------------------------------------
        */

        [
            $salesOrder,
            $salesOrderDetail,
        ] =
            $this->createSalesOrder(
                qty: 10,
                unitPrice: 10000,
            );

        /*
        |--------------------------------------------------------------------------
        | Capture Before Attack
        |--------------------------------------------------------------------------
        */

        $deliveryOrderCountBefore =
            DeliveryOrder::query()
                ->count();

        $deliveryOrderDetailCountBefore =
            DeliveryOrderDetail::query()
                ->count();

        $stockLedgerCountBefore =
            StockLedger::query()
                ->count();

        $journalCountBefore =
            Journal::query()
                ->count();

        $salesOrderStatusBefore =
            $salesOrder->status;

        $salesOrderDetail->refresh();

        $deliveredQtyBefore =
            (float)
            $salesOrderDetail->delivered_qty;

        /*
        |--------------------------------------------------------------------------
        | Execute Attack
        |--------------------------------------------------------------------------
        */

        try {

            app(DeliveryOrderService::class)
                ->create(
                    new DeliveryOrderDTO(
                        salesOrderId:
                            $salesOrder->id,

                        remarks:
                            'Cross-company COGS account attack',

                        warehouseId:
                            $this->data['warehouse_id'],

                        createdBy:
                            $this->data['user_id'],

                        lines: [
                            new DeliveryOrderLineDTO(
                                itemId:
                                    $this->data['item_id'],

                                qty:
                                    10,

                                salesOrderDetailId:
                                    $salesOrderDetail->id,

                                remarks:
                                    'Cross-company COGS attack',
                            ),
                        ],

                        deliveryDate:
                            '2026-08-08',
                    )
                );

            $this->fail(
                'Delivery Order must reject a COGS account from another company.'
            );

        } catch (RuntimeException $exception) {

            $this->assertSame(
                sprintf(
                    'COGS account does not belong to company %d.',
                    $companyAId
                ),
                $exception->getMessage()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Transaction Must Fully Roll Back
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $deliveryOrderCountBefore,
            DeliveryOrder::query()
                ->count()
        );

        $this->assertSame(
            $deliveryOrderDetailCountBefore,
            DeliveryOrderDetail::query()
                ->count()
        );

        $this->assertSame(
            $stockLedgerCountBefore,
            StockLedger::query()
                ->count()
        );

        $this->assertSame(
            $journalCountBefore,
            Journal::query()
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Sales Order Must Remain Unchanged
        |--------------------------------------------------------------------------
        */

        $salesOrder->refresh();
        $salesOrderDetail->refresh();

        $this->assertSame(
            $salesOrderStatusBefore,
            $salesOrder->status
        );

        $this->assertEqualsWithDelta(
            $deliveredQtyBefore,
            (float)
            $salesOrderDetail->delivered_qty,
            0.0001
        );
    }

    public function test_delivery_order_rejects_item_from_another_company():
        void
    {
        /*
        |--------------------------------------------------------------------------
        | Arrange - Normal Company A Sales Order
        |--------------------------------------------------------------------------
        */

        [$salesOrder, $salesOrderDetail] =
            $this->createSalesOrder(
                qty: 20,
                unitPrice: 10000
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
                        'COMP-DO-B',

                    'name' =>
                        'Delivery Order Company B',

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
                        'CAT-DO-B',

                    'name' =>
                        'Delivery Order Category B',

                    'description' =>
                        'Cross-company DO item attack',

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
                        'ITEM-DO-B',

                    'name' =>
                        'Delivery Order Item Company B',

                    'description' =>
                        'Must not enter Company A delivery order',

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
        | Tamper Sales Order Detail
        |--------------------------------------------------------------------------
        |
        | Sales Order tetap Company A tetapi detail sengaja diarahkan ke
        | Item milik Company B.
        |
        */

        $salesOrderDetail->update([
            'item_id' =>
                $itemB->id,
        ]);

        $salesOrderDetail->refresh();

        /*
        |--------------------------------------------------------------------------
        | Snapshot Before Attack
        |--------------------------------------------------------------------------
        */

        $deliveryOrderCountBefore =
            DeliveryOrder::query()
                ->count();

        $deliveryOrderDetailCountBefore =
            DeliveryOrderDetail::query()
                ->count();

        $stockLedgerCountBefore =
            StockLedger::query()
                ->count();

        $journalCountBefore =
            Journal::query()
                ->count();

        $salesOrderStatusBefore =
            $salesOrder->status;

        $deliveredQtyBefore =
            (float)
            $salesOrderDetail->delivered_qty;

        $sequenceBefore =
            DocumentSequence::query()
                ->where(
                    'document_type',
                    'DO'
                )
                ->value(
                    'current_number'
                );

        /*
        |--------------------------------------------------------------------------
        | Execute Attack
        |--------------------------------------------------------------------------
        */

        try {

            app(DeliveryOrderService::class)
                ->create(
                    new DeliveryOrderDTO(
                        salesOrderId:
                            $salesOrder->id,

                        remarks:
                            'Cross-company item attack',

                        warehouseId:
                            $this->data['warehouse_id'],

                        createdBy:
                            $this->data['user_id'],

                        lines: [
                            new DeliveryOrderLineDTO(
                                itemId:
                                    $itemB->id,

                                qty:
                                    10,

                                salesOrderDetailId:
                                    $salesOrderDetail->id,

                                remarks:
                                    'Cross-company item attack',
                            ),
                        ],

                        deliveryDate:
                            '2026-08-08',
                    )
                );

            $this->fail(
                'Delivery Order must reject an item from another company.'
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
            (int) $salesOrder->company_id
        );

        $this->assertNotSame(
            (int) $salesOrder->company_id,
            (int) $itemB->company_id
        );

        $this->assertSame(
            (int) $itemB->id,
            (int) $salesOrderDetail->item_id
        );

        /*
        |--------------------------------------------------------------------------
        | Assert - Transaction Fully Rolled Back
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $deliveryOrderCountBefore,
            DeliveryOrder::query()
                ->count()
        );

        $this->assertSame(
            $deliveryOrderDetailCountBefore,
            DeliveryOrderDetail::query()
                ->count()
        );

        $this->assertSame(
            $stockLedgerCountBefore,
            StockLedger::query()
                ->count()
        );

        $this->assertSame(
            $journalCountBefore,
            Journal::query()
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Assert - Sales Order Unchanged
        |--------------------------------------------------------------------------
        */

        $salesOrder->refresh();
        $salesOrderDetail->refresh();

        $this->assertSame(
            $salesOrderStatusBefore,
            $salesOrder->status
        );

        $this->assertEqualsWithDelta(
            $deliveredQtyBefore,
            (float) $salesOrderDetail->delivered_qty,
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
                    'document_type',
                    'DO'
                )
                ->value(
                    'current_number'
                )
        );

        /*
        |--------------------------------------------------------------------------
        | Assert - Foreign Item Never Reaches DO Detail
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseMissing(
            'delivery_order_details',
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

    private function createSalesOrder(
        float $qty,
        float $unitPrice
    ): array {

        /*
        |--------------------------------------------------------------------------
        | Temporary Customer FK Handling
        |--------------------------------------------------------------------------
        |
        | Customer fixture belum menjadi bagian dari inventory fixture.
        | Kita pertahankan pola test yang sebelumnya sudah berjalan.
        |
        | Nanti customer fixture akan kita rapikan terpisah.
        |
        */

        DB::statement(
            'SET FOREIGN_KEY_CHECKS=0'
        );

        try {

            $so =
                SalesOrder::create([
                    'company_id' =>
                        $this->data['company_id'],

                    'so_no' =>
                        'SO-TEST-'
                        .
                        uniqid(),

                    'customer_id' =>
                        999999,

                    'order_date' =>
                        '2026-08-08',

                    'delivery_date' =>
                        '2026-08-08',

                    'status' =>
                        'APPROVED',

                    'remarks' =>
                        'DO closed loop test',

                    'created_by' =>
                        $this->data['user_id'],
                ]);

        } finally {

            DB::statement(
                'SET FOREIGN_KEY_CHECKS=1'
            );
        }

        $detail =
            SalesOrderDetail::create([
                'sales_order_id' =>
                    $so->id,

                'item_id' =>
                    $this->data['item_id'],

                'qty' =>
                    $qty,

                'unit_price' =>
                    $unitPrice,

                'discount' =>
                    0,

                'delivered_qty' =>
                    0,

                'remarks' =>
                    'SO detail test',
            ]);

        return [
            $so,
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
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
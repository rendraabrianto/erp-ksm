<?php

namespace Tests\Feature;

use App\DTO\InventoryTransactionDTO;
use App\Models\Item;
use App\Models\Warehouse;
use App\Services\InventoryCostingService;
use App\Services\InventoryTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class InventoryMultiWarehouseCostingTest extends TestCase
{
    use RefreshDatabase;

    private array $fixture;

    private Warehouse $warehouseB;

    private InventoryTransactionService $inventoryTransactionService;

    private InventoryCostingService $inventoryCostingService;

    protected function setUp(): void
    {
        parent::setUp();

        /*
        |--------------------------------------------------------------------------
        | Base Fixture
        |--------------------------------------------------------------------------
        |
        | WH-A dari fixture mempunyai kondisi akhir:
        |
        | qty          = 173
        | average cost = 6,000
        |
        */

        $this->fixture =
            InventoryReconciliationTestData::create();

        $this->inventoryTransactionService =
            app(
                InventoryTransactionService::class
            );

        $this->inventoryCostingService =
            app(
                InventoryCostingService::class
            );

        /*
        |--------------------------------------------------------------------------
        | Second Warehouse
        |--------------------------------------------------------------------------
        */

        $this->warehouseB =
            Warehouse::query()
                ->create([
                    'company_id' =>
                        $this->fixture['company_id'],

                    'branch_id' =>
                        $this->fixture['branch_id'],

                    'code' =>
                        'WH-B',

                    'name' =>
                        'Warehouse B',

                    'address' =>
                        'Multi warehouse costing test',

                    'is_active' =>
                        true,
                ]);
    }

    public function test_same_item_can_have_different_average_cost_per_warehouse(): void
    {
        /*
        |--------------------------------------------------------------------------
        | WH-B receives stock @ 8,000
        |--------------------------------------------------------------------------
        */

        $this
            ->inventoryTransactionService
            ->post(
                new InventoryTransactionDTO(
                    warehouseId:
                        $this->warehouseB->id,

                    itemId:
                        $this->fixture['item_id'],

                    referenceType:
                        'MW_TEST_IN',

                    referenceId:
                        1001,

                    qtyIn:
                        20,

                    qtyOut:
                        0,

                    unitCost:
                        8000,

                    remarks:
                        'WH-B initial inbound',

                    transactionDate:
                        '2026-08-08'
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Read Warehouse-Specific States
        |--------------------------------------------------------------------------
        */

        $warehouseAState =
            $this
                ->inventoryCostingService
                ->getCurrentState(
                    warehouseId:
                        $this->fixture['warehouse_id'],

                    itemId:
                        $this->fixture['item_id']
                );

        $warehouseBState =
            $this
                ->inventoryCostingService
                ->getCurrentState(
                    warehouseId:
                        $this->warehouseB->id,

                    itemId:
                        $this->fixture['item_id']
                );

        /*
        |--------------------------------------------------------------------------
        | Assertions
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            173,
            (float)
            $warehouseAState['qty']
        );

        $this->assertEquals(
            6000,
            (float)
            $warehouseAState['average_cost']
        );

        $this->assertEquals(
            20,
            (float)
            $warehouseBState['qty']
        );

        $this->assertEquals(
            8000,
            (float)
            $warehouseBState['average_cost']
        );
    }

    public function test_inbound_in_warehouse_b_does_not_change_warehouse_a_average_cost(): void
    {
        /*
        |--------------------------------------------------------------------------
        | WH-B first inbound
        |--------------------------------------------------------------------------
        */

        $this
            ->inventoryTransactionService
            ->post(
                new InventoryTransactionDTO(
                    warehouseId:
                        $this->warehouseB->id,

                    itemId:
                        $this->fixture['item_id'],

                    referenceType:
                        'MW_TEST_IN',

                    referenceId:
                        2001,

                    qtyIn:
                        20,

                    qtyOut:
                        0,

                    unitCost:
                        8000,

                    remarks:
                        'WH-B first inbound',

                    transactionDate:
                        '2026-08-08'
                )
            );

        /*
        |--------------------------------------------------------------------------
        | WH-B second inbound
        |--------------------------------------------------------------------------
        |
        | 20 @ 8,000 = 160,000
        | 20 @10,000 = 200,000
        |
        | New WH-B:
        |
        | qty   = 40
        | value = 360,000
        | avg   = 9,000
        |
        */

        $this
            ->inventoryTransactionService
            ->post(
                new InventoryTransactionDTO(
                    warehouseId:
                        $this->warehouseB->id,

                    itemId:
                        $this->fixture['item_id'],

                    referenceType:
                        'MW_TEST_IN',

                    referenceId:
                        2002,

                    qtyIn:
                        20,

                    qtyOut:
                        0,

                    unitCost:
                        10000,

                    remarks:
                        'WH-B second inbound',

                    transactionDate:
                        '2026-08-09'
                )
            );

        $warehouseAState =
            $this
                ->inventoryCostingService
                ->getCurrentState(
                    warehouseId:
                        $this->fixture['warehouse_id'],

                    itemId:
                        $this->fixture['item_id']
                );

        $warehouseBState =
            $this
                ->inventoryCostingService
                ->getCurrentState(
                    warehouseId:
                        $this->warehouseB->id,

                    itemId:
                        $this->fixture['item_id']
                );

        $this->assertEquals(
            6000,
            (float)
            $warehouseAState['average_cost']
        );

        $this->assertEquals(
            9000,
            (float)
            $warehouseBState['average_cost']
        );

        $this->assertNotEquals(
            (float)
            $warehouseAState['average_cost'],

            (float)
            $warehouseBState['average_cost']
        );
    }

    public function test_outbound_uses_source_warehouse_average_not_global_item_average(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Poison Global Average
        |--------------------------------------------------------------------------
        |
        | Sengaja kita isi average_cost global dengan nilai salah.
        |
        | Kalau inventory engine masih bergantung pada Item.average_cost,
        | transaksi OUT akan menghasilkan cost yang salah.
        |
        */

        Item::query()
            ->whereKey(
                $this->fixture['item_id']
            )
            ->update([
                'average_cost' =>
                    99999,
            ]);

        /*
        |--------------------------------------------------------------------------
        | Post OUT From WH-A
        |--------------------------------------------------------------------------
        |
        | WH-A authoritative moving average = 6,000.
        |
        */

        $ledger =
            $this
                ->inventoryTransactionService
                ->post(
                    new InventoryTransactionDTO(
                        warehouseId:
                            $this->fixture['warehouse_id'],

                        itemId:
                            $this->fixture['item_id'],

                        referenceType:
                            'MW_TEST_OUT',

                        referenceId:
                            3001,

                        qtyIn:
                            0,

                        qtyOut:
                            10,

                        unitCost:
                            0,

                        remarks:
                            'WH-A outbound isolation test',

                        transactionDate:
                            '2026-08-08'
                    )
                );

        /*
        |--------------------------------------------------------------------------
        | Cost Must Come From WH-A
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            6000,
            (float)
            $ledger->unit_cost
        );

        $this->assertEquals(
            60000,
            (float)
            $ledger->total_cost
        );

        /*
        |--------------------------------------------------------------------------
        | Global Average Must Remain Untouched
        |--------------------------------------------------------------------------
        |
        | InventoryTransactionService tidak boleh menulis ulang field global.
        |
        */

        $item =
            Item::query()
                ->findOrFail(
                    $this->fixture['item_id']
                );

        $this->assertEquals(
            99999,
            (float)
            $item->average_cost
        );
    }

    public function test_activity_in_warehouse_b_does_not_change_warehouse_a_quantity_or_value(): void
    {
        /*
        |--------------------------------------------------------------------------
        | WH-A Before
        |--------------------------------------------------------------------------
        */

        $before =
            $this
                ->inventoryCostingService
                ->getCurrentState(
                    warehouseId:
                        $this->fixture['warehouse_id'],

                    itemId:
                        $this->fixture['item_id']
                );

        /*
        |--------------------------------------------------------------------------
        | WH-B Activity
        |--------------------------------------------------------------------------
        */

        $this
            ->inventoryTransactionService
            ->post(
                new InventoryTransactionDTO(
                    warehouseId:
                        $this->warehouseB->id,

                    itemId:
                        $this->fixture['item_id'],

                    referenceType:
                        'MW_TEST_IN',

                    referenceId:
                        4001,

                    qtyIn:
                        50,

                    qtyOut:
                        0,

                    unitCost:
                        12000,

                    remarks:
                        'Warehouse B independent activity',

                    transactionDate:
                        '2026-08-08'
                )
            );

        /*
        |--------------------------------------------------------------------------
        | WH-A After
        |--------------------------------------------------------------------------
        */

        $after =
            $this
                ->inventoryCostingService
                ->getCurrentState(
                    warehouseId:
                        $this->fixture['warehouse_id'],

                    itemId:
                        $this->fixture['item_id']
                );

        /*
        |--------------------------------------------------------------------------
        | WH-A Must Be Unchanged
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            (float)
            $before['qty'],

            (float)
            $after['qty']
        );

        $this->assertEquals(
            (float)
            $before['average_cost'],

            (float)
            $after['average_cost']
        );

        $this->assertEquals(
            (float)
            $before['value'],

            (float)
            $after['value']
        );
    }
}
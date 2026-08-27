<?php

namespace Tests\Feature;

use App\DTO\InventoryHistoricalReconciliationDTO;
use App\Services\InventoryHistoricalReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class InventoryHistoricalReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    protected function setUp(): void
    {
        parent::setUp();

        $this->data =
            InventoryReconciliationTestData::create();
    }

    private function makeDto():
        InventoryHistoricalReconciliationDTO
    {
        return new InventoryHistoricalReconciliationDTO(
            warehouseId:
                $this->data['warehouse_id'],

            itemId:
                $this->data['item_id'],

            dateFrom:
                '2026-08-01',

            dateTo:
                '2026-08-31',

            inventoryAccountId:
                $this->data['inventory_account_id'],
        );
    }

    public function test_it_recalculates_moving_average_correctly():
        void
    {
        $result = app(
            InventoryHistoricalReconciliationService::class
        )->reconcile(
            $this->makeDto()
        );

        $this->assertEquals(
            173.0,
            $result['final_qty']
        );

        $this->assertEquals(
            1038000.0,
            $result['final_value']
        );

        $this->assertEquals(
            6000.0,
            $result['final_average_cost']
        );
    }

    public function test_it_detects_three_missing_journals():
        void
    {
        $result = app(
            InventoryHistoricalReconciliationService::class
        )->reconcile(
            $this->makeDto()
        );

        $this->assertSame(
            3,
            $result['missing_journal_count']
        );

        $rows = collect($result['rows'])
            ->where(
                'status',
                'MISSING_JOURNAL'
            );

        $this->assertCount(
            3,
            $rows
        );

        $this->assertEqualsCanonicalizing(
            [201, 202, 203],
            $rows
                ->pluck('reference_id')
                ->all()
        );
    }

    public function test_it_detects_two_cost_mismatches():
        void
    {
        $result = app(
            InventoryHistoricalReconciliationService::class
        )->reconcile(
            $this->makeDto()
        );

        $this->assertSame(
            2,
            $result['cost_mismatch_count']
        );

        $rows = collect($result['rows'])
            ->where(
                'status',
                'COST_MISMATCH'
            );

        $this->assertCount(
            2,
            $rows
        );

        $this->assertEqualsCanonicalizing(
            [204, 205],
            $rows
                ->pluck('reference_id')
                ->all()
        );
    }

    public function test_it_detects_three_orphan_journals():
        void
    {
        $result = app(
            InventoryHistoricalReconciliationService::class
        )->reconcile(
            $this->makeDto()
        );

        $this->assertSame(
            3,
            $result['orphan_journal_count']
        );

        $this->assertCount(
            3,
            $result['orphan_journals']
        );

        $this->assertEqualsCanonicalizing(
            [9991, 9992, 9993],
            collect(
                $result['orphan_journals']
            )
                ->pluck('reference_id')
                ->all()
        );
    }

    public function test_it_marks_reconciliation_as_not_reconciled():
        void
    {
        $result = app(
            InventoryHistoricalReconciliationService::class
        )->reconcile(
            $this->makeDto()
        );

        $this->assertFalse(
            $result['is_reconciled']
        );
    }

    public function test_goods_receipt_journal_matches_expected_cost():
        void
    {
        $result = app(
            InventoryHistoricalReconciliationService::class
        )->reconcile(
            $this->makeDto()
        );

        $row = collect($result['rows'])
            ->first(
                fn (array $row) =>
                    $row['reference_type']
                        === 'GOODS_RECEIPT'
                    &&
                    $row['reference_id']
                        === 100
            );

        $this->assertNotNull(
            $row
        );

        $this->assertSame(
            'MATCH',
            $row['status']
        );

        $this->assertEquals(
            700000.0,
            $row['expected_total_cost']
        );

        $this->assertEquals(
            700000.0,
            $row['journal_amount']
        );
    }

    public function test_cost_mismatch_amounts_are_correct():
        void
    {
        $result = app(
            InventoryHistoricalReconciliationService::class
        )->reconcile(
            $this->makeDto()
        );

        $do204 = collect($result['rows'])
            ->firstWhere(
                'reference_id',
                204
            );

        $do205 = collect($result['rows'])
            ->firstWhere(
                'reference_id',
                205
            );

        $this->assertEquals(
            30000.0,
            $do204['expected_total_cost']
        );

        $this->assertEquals(
            20000.0,
            $do204['journal_amount']
        );

        $this->assertEquals(
            -10000.0,
            $do204['journal_difference']
        );

        $this->assertEquals(
            12000.0,
            $do205['expected_total_cost']
        );

        $this->assertEquals(
            8000.0,
            $do205['journal_amount']
        );

        $this->assertEquals(
            -4000.0,
            $do205['journal_difference']
        );
    }

    public function test_summary_totals_are_correct():
        void
    {
        $result = app(
            InventoryHistoricalReconciliationService::class
        )->reconcile(
            $this->makeDto()
        );

        $this->assertEquals(
            1200000.0,
            $result['total_expected_inbound']
        );

        $this->assertEquals(
            700000.0,
            $result['total_posted_inbound']
        );

        $this->assertEquals(
            162000.0,
            $result['total_expected_outbound']
        );

        $this->assertEquals(
            28000.0,
            $result['total_posted_outbound']
        );
    }
}
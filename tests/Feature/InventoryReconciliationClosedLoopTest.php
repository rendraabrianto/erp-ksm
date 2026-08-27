<?php

namespace Tests\Feature;

use App\DTO\InventoryHistoricalReconciliationDTO;
use App\DTO\InventoryReconciliationAdjustmentDTO;
use App\Models\Journal;
use App\Services\InventoryHistoricalReconciliationService;
use App\Services\InventoryReconciliationAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class InventoryReconciliationClosedLoopTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    private InventoryHistoricalReconciliationDTO $historicalDto;

    private InventoryReconciliationAdjustmentDTO $adjustmentDto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->data =
            InventoryReconciliationTestData::create();

        $this->historicalDto =
            new InventoryHistoricalReconciliationDTO(
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

        $this->adjustmentDto =
            new InventoryReconciliationAdjustmentDTO(
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

                cogsAccountId:
                    $this->data['cogs_account_id'],

                grniAccountId:
                    $this->data['grni_account_id'],
            );
    }

    public function test_apply_closes_all_reconciliation_anomalies(): void
    {
        $historicalService = app(
            InventoryHistoricalReconciliationService::class
        );

        $adjustmentService = app(
            InventoryReconciliationAdjustmentService::class
        );

        $before =
            $historicalService->reconcile(
                $this->historicalDto
            );

        $this->assertSame(
            3,
            $before['missing_journal_count']
        );

        $this->assertSame(
            2,
            $before['cost_mismatch_count']
        );

        $this->assertSame(
            3,
            $before['orphan_journal_count']
        );

        $this->assertFalse(
            $before['is_reconciled']
        );

        $apply =
            $adjustmentService->apply(
                $this->adjustmentDto,
                $this->data['user_id']
            );

        $this->assertSame(
            'POSTED',
            $apply['status']
        );

        $this->assertSame(
            8,
            $apply['posted_count']
        );

        $after =
            $historicalService->reconcile(
                $this->historicalDto
            );

        $this->assertSame(
            0,
            $after['missing_journal_count']
        );

        $this->assertSame(
            0,
            $after['cost_mismatch_count']
        );

        $this->assertSame(
            0,
            $after['orphan_journal_count']
        );

        $this->assertTrue(
            $after['is_reconciled']
        );
    }

    public function test_inventory_valuation_does_not_change_after_journal_adjustment():
        void
    {
        $historicalService = app(
            InventoryHistoricalReconciliationService::class
        );

        $adjustmentService = app(
            InventoryReconciliationAdjustmentService::class
        );

        $before =
            $historicalService->reconcile(
                $this->historicalDto
            );

        $adjustmentService->apply(
            $this->adjustmentDto,
            $this->data['user_id']
        );

        $after =
            $historicalService->reconcile(
                $this->historicalDto
            );

        $this->assertEquals(
            $before['final_qty'],
            $after['final_qty']
        );

        $this->assertEquals(
            $before['final_value'],
            $after['final_value']
        );

        $this->assertEquals(
            $before['final_average_cost'],
            $after['final_average_cost']
        );

        $this->assertEquals(
            173.0,
            $after['final_qty']
        );

        $this->assertEquals(
            1038000.0,
            $after['final_value']
        );

        $this->assertEquals(
            6000.0,
            $after['final_average_cost']
        );
    }

    public function test_second_apply_is_idempotent(): void
    {
        $service = app(
            InventoryReconciliationAdjustmentService::class
        );

        $first =
            $service->apply(
                $this->adjustmentDto,
                $this->data['user_id']
            );

        $this->assertSame(
            'POSTED',
            $first['status']
        );

        $this->assertSame(
            8,
            $first['posted_count']
        );

        $journalCountAfterFirst =
            Journal::count();

        $second =
            $service->apply(
                $this->adjustmentDto,
                $this->data['user_id']
            );

        $this->assertSame(
            'APPLY',
            $second['mode']
        );

        $this->assertSame(
            'NOTHING_TO_POST',
            $second['status']
        );

        $this->assertSame(
            0,
            $second['posted_count']
        );

        $this->assertCount(
            0,
            $second['journals']
        );

        $this->assertSame(
            $journalCountAfterFirst,
            Journal::count()
        );
    }

    public function test_preview_is_empty_after_successful_apply(): void
    {
        $service = app(
            InventoryReconciliationAdjustmentService::class
        );

        $service->apply(
            $this->adjustmentDto,
            $this->data['user_id']
        );

        $preview =
            $service->preview(
                $this->adjustmentDto
            );

        $this->assertSame(
            0,
            $preview['proposal_count']
        );

        $this->assertSame(
            0,
            $preview['recover_missing_count']
        );

        $this->assertSame(
            0,
            $preview['correct_mismatch_count']
        );

        $this->assertSame(
            0,
            $preview['reverse_orphan_count']
        );

        $this->assertTrue(
            $preview[
                'reconciliation_before'
            ]['is_reconciled']
        );
    }

    public function test_no_problem_rows_remain_after_apply(): void
    {
        $historicalService = app(
            InventoryHistoricalReconciliationService::class
        );

        $adjustmentService = app(
            InventoryReconciliationAdjustmentService::class
        );

        $adjustmentService->apply(
            $this->adjustmentDto,
            $this->data['user_id']
        );

        $after =
            $historicalService->reconcile(
                $this->historicalDto
            );

        $problemRows =
            collect(
                $after['rows']
            )->where(
                'status',
                '!=',
                'MATCH'
            );

        $this->assertCount(
            0,
            $problemRows
        );

        $this->assertCount(
            0,
            $after['orphan_journals']
        );
    }
}
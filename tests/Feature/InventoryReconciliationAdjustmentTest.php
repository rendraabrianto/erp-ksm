<?php

namespace Tests\Feature;

use App\DTO\InventoryReconciliationAdjustmentDTO;
use App\Models\Journal;
use App\Services\InventoryReconciliationAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Services\JournalPostingService;
use Mockery;

class InventoryReconciliationAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    private InventoryReconciliationAdjustmentDTO $dto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->data =
            InventoryReconciliationTestData::create();

        $this->dto =
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

    private function service():
        InventoryReconciliationAdjustmentService
    {
        return app(
            InventoryReconciliationAdjustmentService::class
        );
    }

    public function test_preview_produces_expected_eight_proposals():
        void
    {
        $preview =
            $this->service()->preview(
                $this->dto
            );

        $this->assertSame(
            'DRY_RUN',
            $preview['mode']
        );

        $this->assertSame(
            8,
            $preview['proposal_count']
        );

        $this->assertSame(
            3,
            $preview['recover_missing_count']
        );

        $this->assertSame(
            2,
            $preview['correct_mismatch_count']
        );

        $this->assertSame(
            3,
            $preview['reverse_orphan_count']
        );

        $this->assertFalse(
            $preview[
                'reconciliation_before'
            ]['is_reconciled']
        );
    }

    public function test_preview_does_not_modify_database():
        void
    {
        $before =
            Journal::count();

        $this->service()->preview(
            $this->dto
        );

        $after =
            Journal::count();

        $this->assertSame(
            $before,
            $after
        );
    }

    public function test_apply_posts_eight_adjustment_journals():
        void
    {
        $before =
            Journal::count();

        $result =
            $this->service()->apply(
                $this->dto,
                $this->data['user_id']
            );

        $this->assertSame(
            'APPLY',
            $result['mode']
        );

        $this->assertSame(
            'POSTED',
            $result['status']
        );

        $this->assertSame(
            8,
            $result['posted_count']
        );

        $this->assertCount(
            8,
            $result['journals']
        );

        $this->assertSame(
            $before + 8,
            Journal::count()
        );
    }

    public function test_apply_creates_correct_journal_purposes():
        void
    {
        $this->service()->apply(
            $this->dto,
            $this->data['user_id']
        );

        $this->assertSame(
            3,
            Journal::where(
                'journal_purpose',
                'RECOVERY'
            )->count()
        );

        $this->assertSame(
            2,
            Journal::where(
                'journal_purpose',
                'COST_CORRECTION'
            )->count()
        );

        $this->assertSame(
            3,
            Journal::where(
                'journal_purpose',
                'REVERSAL'
            )->count()
        );
    }

    public function test_all_adjustment_journals_are_balanced():
        void
    {
        $result =
            $this->service()->apply(
                $this->dto,
                $this->data['user_id']
            );

        $journalIds =
            collect(
                $result['journals']
            )->pluck('journal_id');

        $journals =
            Journal::with('details')
                ->whereIn(
                    'id',
                    $journalIds
                )
                ->get();

        $this->assertCount(
            8,
            $journals
        );

        foreach ($journals as $journal) {

            $debit =
                round(
                    (float)
                    $journal->details
                        ->sum('debit'),
                    2
                );

            $credit =
                round(
                    (float)
                    $journal->details
                        ->sum('credit'),
                    2
                );

            $this->assertEquals(
                $debit,
                $credit,
                'Journal '
                .
                $journal->journal_no
                .
                ' is not balanced.'
            );
        }
    }

    public function test_all_adjustment_journals_have_reconciliation_keys():
        void
    {
        $result =
            $this->service()->apply(
                $this->dto,
                $this->data['user_id']
            );

        $journalIds =
            collect(
                $result['journals']
            )->pluck('journal_id');

        $journals =
            Journal::query()
                ->whereIn(
                    'id',
                    $journalIds
                )
                ->get();

        foreach ($journals as $journal) {

            $this->assertNotNull(
                $journal->reconciliation_key
            );

            $this->assertNotSame(
                '',
                $journal->reconciliation_key
            );
        }

        $this->assertSame(
            8,
            $journals
                ->pluck('reconciliation_key')
                ->unique()
                ->count()
        );
    }

    public function test_reversal_journals_reference_their_source_journals():
        void
    {
        $this->service()->apply(
            $this->dto,
            $this->data['user_id']
        );

        $reversals =
            Journal::query()
                ->where(
                    'journal_purpose',
                    'REVERSAL'
                )
                ->get();

        $this->assertCount(
            3,
            $reversals
        );

        foreach ($reversals as $journal) {

            $this->assertNotNull(
                $journal->source_journal_id
            );

            $this->assertTrue(
                Journal::whereKey(
                    $journal->source_journal_id
                )->exists()
            );
        }
    }

    public function test_reversal_journal_lines_are_exact_opposite_of_source():
        void
    {
        $this->service()->apply(
            $this->dto,
            $this->data['user_id']
        );

        $reversals =
            Journal::with('details')
                ->where(
                    'journal_purpose',
                    'REVERSAL'
                )
                ->get();

        foreach ($reversals as $reversal) {

            $source =
                Journal::with('details')
                    ->findOrFail(
                        $reversal->source_journal_id
                    );

            foreach (
                $source->details
                as $sourceLine
            ) {

                $reverseLine =
                    $reversal->details
                        ->firstWhere(
                            'account_id',
                            $sourceLine->account_id
                        );

                $this->assertNotNull(
                    $reverseLine
                );

                $this->assertEquals(
                    (float)
                    $sourceLine->debit,
                    (float)
                    $reverseLine->credit
                );

                $this->assertEquals(
                    (float)
                    $sourceLine->credit,
                    (float)
                    $reverseLine->debit
                );
            }
        }
    }

    public function test_apply_advances_journal_sequence_exactly_by_number_of_posted_journals():
    void
    {
        $service = app(
            InventoryReconciliationAdjustmentService::class
        );

        $before = DB::table('document_sequences')
            ->where(
                'document_type',
                'JV'
            )
            ->value('current_number');

        $result = $service->apply(
            $this->dto,
            $this->data['user_id']
        );

        $after = DB::table('document_sequences')
            ->where(
                'document_type',
                'JV'
            )
            ->value('current_number');

        $this->assertSame(
            8,
            $result['posted_count']
        );

        $this->assertSame(
            8,
            (int) $after - (int) $before
        );

        $this->assertSame(
            $result['posted_count'],
            (int) $after - (int) $before
        );
    }

    public function test_apply_rolls_back_entire_batch_when_posting_fails_midway():
        void
    {
        /*
        |--------------------------------------------------------------------------
        | DATABASE STATE BEFORE APPLY
        |--------------------------------------------------------------------------
        */

        $journalCountBefore =
            Journal::count();

        $journalDetailCountBefore =
            DB::table('journal_details')
                ->count();

        $auditLogCountBefore =
            DB::table('audit_logs')
                ->count();

        $sequenceBefore =
            (int)
            DB::table('document_sequences')
                ->where(
                    'document_type',
                    'JV'
                )
                ->value(
                    'current_number'
                );

        /*
        |--------------------------------------------------------------------------
        | REAL POSTING SERVICE
        |--------------------------------------------------------------------------
        |
        | Journal 1-3 benar-benar kita posting memakai service asli.
        |
        */

        $realPostingService =
            app(
                JournalPostingService::class
            );

        /*
        |--------------------------------------------------------------------------
        | MOCK POSTING SERVICE
        |--------------------------------------------------------------------------
        |
        | Call 1 = real post
        | Call 2 = real post
        | Call 3 = real post
        | Call 4 = FORCE ERROR
        |
        */

        $callCount = 0;

        $mockPostingService =
            Mockery::mock(
                JournalPostingService::class
            );

        $mockPostingService
            ->shouldReceive('post')
            ->times(4)
            ->andReturnUsing(
                function ($entry)
                use (
                    &$callCount,
                    $realPostingService
                ) {

                    $callCount++;

                    if ($callCount === 4) {

                        throw new \RuntimeException(
                            'Forced reconciliation posting failure'
                        );
                    }

                    return
                        $realPostingService
                            ->post($entry);
                }
            );

        /*
        |--------------------------------------------------------------------------
        | REPLACE DEPENDENCY
        |--------------------------------------------------------------------------
        */

        $this->app->instance(
            JournalPostingService::class,
            $mockPostingService
        );

        /*
        |--------------------------------------------------------------------------
        | RESOLVE ADJUSTMENT SERVICE AFTER MOCK
        |--------------------------------------------------------------------------
        |
        | Penting:
        | resolve setelah dependency diganti.
        |
        */

        $service =
            app(
                InventoryReconciliationAdjustmentService::class
            );

        /*
        |--------------------------------------------------------------------------
        | APPLY MUST FAIL
        |--------------------------------------------------------------------------
        */

        try {

            $service->apply(
                $this->dto,
                $this->data['user_id']
            );

            $this->fail(
                'Expected reconciliation apply to fail.'
            );

        } catch (\RuntimeException $exception) {

            $this->assertSame(
                'Forced reconciliation posting failure',
                $exception->getMessage()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | FAILURE REALLY OCCURRED ON JOURNAL #4
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            4,
            $callCount
        );

        /*
        |--------------------------------------------------------------------------
        | JOURNAL MUST ROLLBACK
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $journalCountBefore,
            Journal::count()
        );

        /*
        |--------------------------------------------------------------------------
        | JOURNAL DETAILS MUST ROLLBACK
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $journalDetailCountBefore,
            DB::table('journal_details')
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | AUDIT LOG MUST ROLLBACK
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $auditLogCountBefore,
            DB::table('audit_logs')
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | DOCUMENT SEQUENCE MUST ROLLBACK
        |--------------------------------------------------------------------------
        */

        $sequenceAfter =
            (int)
            DB::table('document_sequences')
                ->where(
                    'document_type',
                    'JV'
                )
                ->value(
                    'current_number'
                );

        $this->assertSame(
            $sequenceBefore,
            $sequenceAfter
        );

        /*
        |--------------------------------------------------------------------------
        | NO PARTIAL ADJUSTMENT JOURNAL MAY EXIST
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            0,
            Journal::query()
                ->whereIn(
                    'journal_purpose',
                    [
                        'RECOVERY',
                        'COST_CORRECTION',
                        'REVERSAL',
                    ]
                )
                ->count()
        );
    }
}
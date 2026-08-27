<?php

namespace App\Services;
use App\DTO\JournalEntryDTO;
use App\DTO\JournalLineDTO;
use App\Models\Account;
use Illuminate\Support\Facades\DB;

use App\DTO\InventoryHistoricalReconciliationDTO;
use App\DTO\InventoryReconciliationAdjustmentDTO;
use App\Repositories\Contracts\InventoryHistoricalReconciliationRepositoryInterface;

class InventoryReconciliationAdjustmentService
{
    public function __construct(
        private InventoryHistoricalReconciliationService $historicalService,
        private InventoryHistoricalReconciliationRepositoryInterface $repository,
        private JournalPostingService $journalPostingService,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | PREVIEW / DRY RUN
    |--------------------------------------------------------------------------
    |
    | Method ini TIDAK mengubah database.
    | Hanya menghasilkan proposal journal recovery/correction/reversal.
    |
    */

    public function preview(
        InventoryReconciliationAdjustmentDTO $dto
    ): array {

        $historicalDto =
            new InventoryHistoricalReconciliationDTO(
                warehouseId: $dto->warehouseId,
                itemId: $dto->itemId,
                dateFrom: $dto->dateFrom,
                dateTo: $dto->dateTo,
                inventoryAccountId: $dto->inventoryAccountId,
            );

        $reconciliation =
            $this->historicalService
                ->reconcile($historicalDto);

        /*
        |--------------------------------------------------------------------------
        | LOAD JOURNALS
        |--------------------------------------------------------------------------
        |
        | Dibutuhkan untuk reversal orphan journal.
        |
        */

        $journals =
            $this->repository
                ->getInventoryJournals($historicalDto);

        $proposals = [];

        /*
        |--------------------------------------------------------------------------
        | MISSING JOURNAL & COST MISMATCH
        |--------------------------------------------------------------------------
        */

        foreach (
            $reconciliation['rows']
            as $row
        ) {

            if (
                $row['status']
                ===
                'MISSING_JOURNAL'
            ) {

                $proposal =
                    $this->buildMissingJournalProposal(
                        $row,
                        $dto
                    );

                if ($proposal !== null) {
                    $proposals[] = $proposal;
                }
            }

            if (
                $row['status']
                ===
                'COST_MISMATCH'
            ) {

                $proposal =
                    $this->buildCostMismatchProposal(
                        $row,
                        $dto
                    );

                if ($proposal !== null) {
                    $proposals[] = $proposal;
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | ORPHAN JOURNAL
        |--------------------------------------------------------------------------
        */

        foreach (
            $reconciliation['orphan_journals']
            as $orphan
        ) {

            $journal =
                $journals->firstWhere(
                    'id',
                    $orphan['journal_id']
                );

            if (! $journal) {
                continue;
            }

            $proposals[] =
                $this->buildOrphanReversalProposal(
                    $journal
                );
        }

        /*
        |--------------------------------------------------------------------------
        | SUMMARY
        |--------------------------------------------------------------------------
        */

        $missingCount =
            collect($proposals)
                ->where(
                    'action',
                    'RECOVER_MISSING_JOURNAL'
                )
                ->count();

        $mismatchCount =
            collect($proposals)
                ->where(
                    'action',
                    'CORRECT_COST_MISMATCH'
                )
                ->count();

        $orphanCount =
            collect($proposals)
                ->where(
                    'action',
                    'REVERSE_ORPHAN_JOURNAL'
                )
                ->count();

        return [

            'mode' =>
                'DRY_RUN',

            'warehouse_id' =>
                $dto->warehouseId,

            'item_id' =>
                $dto->itemId,

            'date_from' =>
                $dto->dateFrom,

            'date_to' =>
                $dto->dateTo,

            'reconciliation_before' => [

                'missing_journal_count' =>
                    $reconciliation[
                        'missing_journal_count'
                    ],

                'cost_mismatch_count' =>
                    $reconciliation[
                        'cost_mismatch_count'
                    ],

                'orphan_journal_count' =>
                    $reconciliation[
                        'orphan_journal_count'
                    ],

                'is_reconciled' =>
                    $reconciliation[
                        'is_reconciled'
                    ],

            ],

            'proposal_count' =>
                count($proposals),

            'recover_missing_count' =>
                $missingCount,

            'correct_mismatch_count' =>
                $mismatchCount,

            'reverse_orphan_count' =>
                $orphanCount,

            'proposals' =>
                $proposals,
        ];
    }

    public function apply(
        InventoryReconciliationAdjustmentDTO $dto,
        int $createdBy
    ): array {

        return DB::transaction(
            function () use (
                $dto,
                $createdBy
            ) {

                /*
                |--------------------------------------------------------------------------
                | REBUILD PREVIEW INSIDE TRANSACTION
                |--------------------------------------------------------------------------
                */

                $preview =
                    $this->preview($dto);

                if (
                    $preview['proposal_count']
                    ===
                    0
                ) {
                    return [
                        'mode' => 'APPLY',
                        'status' => 'NOTHING_TO_POST',
                        'posted_count' => 0,
                        'journals' => [],
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | ACCOUNT MAP
                |--------------------------------------------------------------------------
                |
                | Preview memakai account_id.
                | JournalPostingService memakai accountCode.
                |
                */

                $accountIds =
                    collect(
                        $preview['proposals']
                    )
                        ->flatMap(
                            fn ($proposal) =>
                                collect(
                                    $proposal['lines']
                                )->pluck('account_id')
                        )
                        ->unique()
                        ->values();

                $accounts =
                    Account::query()
                        ->whereIn(
                            'id',
                            $accountIds
                        )
                        ->get()
                        ->keyBy('id');

                if (
                    $accounts->count()
                    !==
                    $accountIds->count()
                ) {
                    throw new \RuntimeException(
                        'One or more reconciliation accounts do not exist.'
                    );
                }

                $postedJournals = [];

                /*
                |--------------------------------------------------------------------------
                | POST EACH PROPOSAL
                |--------------------------------------------------------------------------
                */

                foreach (
                    $preview['proposals']
                    as $proposal
                ) {

                    $reconciliationKey =
                        $this->buildReconciliationKey(
                            $proposal,
                            $dto
                        );

                    $lines = [];

                    foreach (
                        $proposal['lines']
                        as $line
                    ) {

                        $account =
                            $accounts->get(
                                (int)
                                $line['account_id']
                            );

                        if (! $account) {
                            throw new \RuntimeException(
                                'Account not found: '
                                .
                                $line['account_id']
                            );
                        }

                        $lines[] =
                            new JournalLineDTO(
                                accountCode:
                                    $account->code,

                                quantity:
                                    0.0,

                                unitPrice:
                                    0.0,

                                debit:
                                    round(
                                        (float)
                                        $line['debit'],
                                        2
                                    ),

                                credit:
                                    round(
                                        (float)
                                        $line['credit'],
                                        2
                                    ),

                                description:
                                    $line['description']
                                    ?? null,
                            );
                    }

                    $entry =
                        new JournalEntryDTO(
                            referenceType:
                                $proposal[
                                    'reference_type'
                                ],

                            referenceId:
                                (int)
                                $proposal[
                                    'reference_id'
                                ],

                            description:
                                $proposal[
                                    'description'
                                ],

                            createdBy:
                                $createdBy,

                            lines:
                                $lines,

                            journalDate:
                                $proposal[
                                    'journal_date'
                                ],

                            journalPurpose:
                                $this->resolveJournalPurpose(
                                    $proposal['action']
                                ),

                            sourceJournalId:
                                isset(
                                    $proposal[
                                        'source_journal_id'
                                    ]
                                )
                                    ? (int)
                                        $proposal[
                                            'source_journal_id'
                                        ]
                                    : null,

                            reconciliationKey:
                                $reconciliationKey,
                        );

                    $journal =
                        $this
                            ->journalPostingService
                            ->post($entry);

                    $postedJournals[] = [

                        'journal_id' =>
                            (int) $journal->id,

                        'journal_no' =>
                            $journal->journal_no,

                        'journal_purpose' =>
                            $journal->journal_purpose,

                        'reference_type' =>
                            $journal->reference_type,

                        'reference_id' =>
                            (int)
                            $journal->reference_id,

                        'reconciliation_key' =>
                            $journal->reconciliation_key,
                    ];
                }

                return [

                    'mode' =>
                        'APPLY',

                    'status' =>
                        'POSTED',

                    'posted_count' =>
                        count(
                            $postedJournals
                        ),

                    'journals' =>
                        $postedJournals,
                ];
            }
        );
    }

    protected function resolveJournalPurpose(
        string $action
    ): string {

        return match ($action) {

            'RECOVER_MISSING_JOURNAL' =>
                'RECOVERY',

            'CORRECT_COST_MISMATCH' =>
                'COST_CORRECTION',

            'REVERSE_ORPHAN_JOURNAL' =>
                'REVERSAL',

            default =>
                throw new \RuntimeException(
                    'Unsupported reconciliation action: '
                    .
                    $action
                ),
        };
    }
    /*
    |--------------------------------------------------------------------------
    | MISSING JOURNAL
    |--------------------------------------------------------------------------
    */

    protected function buildMissingJournalProposal(
        array $row,
        InventoryReconciliationAdjustmentDTO $dto
    ): ?array {

        $amount =
            round(
                (float)
                $row['expected_total_cost'],
                2
            );

        if ($amount <= 0) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | DELIVERY ORDER
        |--------------------------------------------------------------------------
        */

        if (
            $row['reference_type']
            ===
            'DELIVERY_ORDER'
        ) {

            return [

                'action' =>
                    'RECOVER_MISSING_JOURNAL',

                'reference_type' =>
                    'DELIVERY_ORDER',

                'reference_id' =>
                    (int)
                    $row['reference_id'],

                'stock_ledger_id' =>
                    (int)
                    $row['stock_ledger_id'],

                'journal_date' =>
                    $this->normalizeDate(
                        $row['transaction_date']
                    ),

                'expected_amount' =>
                    $amount,

                'posted_amount' =>
                    0.0,

                'adjustment_amount' =>
                    $amount,

                'description' =>
                    'Recovery missing journal Delivery Order',

                'lines' => [

                    [
                        'account_id' =>
                            $dto->cogsAccountId,

                        'debit' =>
                            $amount,

                        'credit' =>
                            0.0,

                        'description' =>
                            'HPP Recovery',
                    ],

                    [
                        'account_id' =>
                            $dto->inventoryAccountId,

                        'debit' =>
                            0.0,

                        'credit' =>
                            $amount,

                        'description' =>
                            'Persediaan Recovery',
                    ],
                ],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | GOODS RECEIPT
        |--------------------------------------------------------------------------
        */

        if (
            $row['reference_type']
            ===
            'GOODS_RECEIPT'
        ) {

            return [

                'action' =>
                    'RECOVER_MISSING_JOURNAL',

                'reference_type' =>
                    'GOODS_RECEIPT',

                'reference_id' =>
                    (int)
                    $row['reference_id'],

                'stock_ledger_id' =>
                    (int)
                    $row['stock_ledger_id'],

                'journal_date' =>
                    $this->normalizeDate(
                        $row['transaction_date']
                    ),

                'expected_amount' =>
                    $amount,

                'posted_amount' =>
                    0.0,

                'adjustment_amount' =>
                    $amount,

                'description' =>
                    'Recovery missing journal Goods Receipt',

                'lines' => [

                    [
                        'account_id' =>
                            $dto->inventoryAccountId,

                        'debit' =>
                            $amount,

                        'credit' =>
                            0.0,

                        'description' =>
                            'Inventory Recovery',
                    ],

                    [
                        'account_id' =>
                            $dto->grniAccountId,

                        'debit' =>
                            0.0,

                        'credit' =>
                            $amount,

                        'description' =>
                            'GRNI Recovery',
                    ],
                ],
            ];
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | COST MISMATCH
    |--------------------------------------------------------------------------
    */

    protected function buildCostMismatchProposal(
        array $row,
        InventoryReconciliationAdjustmentDTO $dto
    ): ?array {

        $expected =
            round(
                (float)
                $row['expected_total_cost'],
                2
            );

        $posted =
            round(
                (float)
                ($row['journal_amount'] ?? 0),
                2
            );

        $difference =
            round(
                $expected - $posted,
                2
            );

        if (
            abs($difference) <= 0.01
        ) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | DELIVERY ORDER
        |--------------------------------------------------------------------------
        */

        if (
            $row['reference_type']
            ===
            'DELIVERY_ORDER'
        ) {

            /*
            | Positive:
            |
            | Expected > Posted
            |
            | Tambah HPP dan kurangi Inventory.
            */

            if ($difference > 0) {

                $lines = [

                    [
                        'account_id' =>
                            $dto->cogsAccountId,

                        'debit' =>
                            $difference,

                        'credit' =>
                            0.0,

                        'description' =>
                            'HPP Cost Correction',
                    ],

                    [
                        'account_id' =>
                            $dto->inventoryAccountId,

                        'debit' =>
                            0.0,

                        'credit' =>
                            $difference,

                        'description' =>
                            'Inventory Cost Correction',
                    ],
                ];

            } else {

                /*
                | Negative:
                |
                | Posted > Expected
                |
                | Balik kelebihan posting.
                */

                $correction =
                    abs($difference);

                $lines = [

                    [
                        'account_id' =>
                            $dto->inventoryAccountId,

                        'debit' =>
                            $correction,

                        'credit' =>
                            0.0,

                        'description' =>
                            'Inventory Cost Correction',
                    ],

                    [
                        'account_id' =>
                            $dto->cogsAccountId,

                        'debit' =>
                            0.0,

                        'credit' =>
                            $correction,

                        'description' =>
                            'HPP Cost Correction',
                    ],
                ];
            }

            return [

                'action' =>
                    'CORRECT_COST_MISMATCH',

                'reference_type' =>
                    'DELIVERY_ORDER',

                'reference_id' =>
                    (int)
                    $row['reference_id'],

                'stock_ledger_id' =>
                    (int)
                    $row['stock_ledger_id'],

                'journal_date' =>
                    $this->normalizeDate(
                        $row['transaction_date']
                    ),

                'expected_amount' =>
                    $expected,

                'posted_amount' =>
                    $posted,

                'adjustment_amount' =>
                    abs($difference),

                'direction' =>
                    $difference > 0
                        ? 'UNDER_POSTED'
                        : 'OVER_POSTED',

                'description' =>
                    'Correction inventory costing mismatch',

                'lines' =>
                    $lines,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | GOODS RECEIPT
        |--------------------------------------------------------------------------
        */

        if (
            $row['reference_type']
            ===
            'GOODS_RECEIPT'
        ) {

            if ($difference > 0) {

                $lines = [

                    [
                        'account_id' =>
                            $dto->inventoryAccountId,

                        'debit' =>
                            $difference,

                        'credit' =>
                            0.0,

                        'description' =>
                            'Inventory Cost Correction',
                    ],

                    [
                        'account_id' =>
                            $dto->grniAccountId,

                        'debit' =>
                            0.0,

                        'credit' =>
                            $difference,

                        'description' =>
                            'GRNI Cost Correction',
                    ],
                ];

            } else {

                $correction =
                    abs($difference);

                $lines = [

                    [
                        'account_id' =>
                            $dto->grniAccountId,

                        'debit' =>
                            $correction,

                        'credit' =>
                            0.0,

                        'description' =>
                            'GRNI Cost Correction',
                    ],

                    [
                        'account_id' =>
                            $dto->inventoryAccountId,

                        'debit' =>
                            0.0,

                        'credit' =>
                            $correction,

                        'description' =>
                            'Inventory Cost Correction',
                    ],
                ];
            }

            return [

                'action' =>
                    'CORRECT_COST_MISMATCH',

                'reference_type' =>
                    'GOODS_RECEIPT',

                'reference_id' =>
                    (int)
                    $row['reference_id'],

                'stock_ledger_id' =>
                    (int)
                    $row['stock_ledger_id'],

                'journal_date' =>
                    $this->normalizeDate(
                        $row['transaction_date']
                    ),

                'expected_amount' =>
                    $expected,

                'posted_amount' =>
                    $posted,

                'adjustment_amount' =>
                    abs($difference),

                'direction' =>
                    $difference > 0
                        ? 'UNDER_POSTED'
                        : 'OVER_POSTED',

                'description' =>
                    'Correction Goods Receipt costing mismatch',

                'lines' =>
                    $lines,
            ];
        }

        return null;
    }

    protected function buildReconciliationKey(
        array $proposal,
        InventoryReconciliationAdjustmentDTO $dto
    ): string {

        /*
        |--------------------------------------------------------------------------
        | ORPHAN REVERSAL
        |--------------------------------------------------------------------------
        |
        | Harus unik per source journal.
        | Ini penting karena DO #999 mempunyai dua orphan journal.
        |
        */

        if (
            $proposal['action']
            ===
            'REVERSE_ORPHAN_JOURNAL'
        ) {

            return sprintf(
                'INVREC:REVERSAL:JOURNAL:%d',
                (int)
                $proposal['source_journal_id']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | RECOVERY / COST CORRECTION
        |--------------------------------------------------------------------------
        */

        return sprintf(
            'INVREC:%s:%s:%d:ITEM:%d:WH:%d',
            $proposal['action'],
            $proposal['reference_type'],
            (int)
            $proposal['reference_id'],
            $dto->itemId,
            $dto->warehouseId
        );
    }
    /*
    |--------------------------------------------------------------------------
    | ORPHAN JOURNAL REVERSAL
    |--------------------------------------------------------------------------
    |
    | Reversal TIDAK menebak akun.
    |
    | Semua detail jurnal asli dibalik:
    |
    | Debit asli  -> Credit reversal
    | Credit asli -> Debit reversal
    |
    */

    protected function buildOrphanReversalProposal(
        $journal
    ): array {

        $lines = [];

        foreach (
            $journal->details
            as $detail
        ) {

            $lines[] = [

                'account_id' =>
                    (int)
                    $detail->account_id,

                'debit' =>
                    round(
                        (float)
                        $detail->credit,
                        2
                    ),

                'credit' =>
                    round(
                        (float)
                        $detail->debit,
                        2
                    ),

                'description' =>
                    'Reversal - '
                    .
                    ($detail->description ?? ''),
            ];
        }

        return [

            'action' =>
                'REVERSE_ORPHAN_JOURNAL',

            'source_journal_id' =>
                (int)
                $journal->id,

            'source_journal_no' =>
                $journal->journal_no,

            'reference_type' =>
                $journal->reference_type,

            'reference_id' =>
                (int)
                $journal->reference_id,

            'journal_date' =>
                $this->normalizeDate(
                    $journal->journal_date
                ),

            'description' =>
                'Reversal orphan journal '
                .
                $journal->journal_no,

            'lines' =>
                $lines,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | NORMALIZE DATE
    |--------------------------------------------------------------------------
    */

    protected function normalizeDate(
        $date
    ): string {

        if (
            $date instanceof
            \DateTimeInterface
        ) {
            return $date->format(
                'Y-m-d'
            );
        }

        return (string) $date;
    }
}
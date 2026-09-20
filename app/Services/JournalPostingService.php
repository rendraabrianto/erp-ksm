<?php

namespace App\Services;

use App\DTO\JournalEntryDTO;
use App\Models\Account;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Repositories\Contracts\JournalRepositoryInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class JournalPostingService
{
    public function __construct(
        private JournalRepositoryInterface $journalRepository,
        private DocumentSequenceService $documentSequenceService,
        private AuditLogService $auditLogService,
    ) {}

    public function validateBalance(
        JournalEntryDTO $entry
    ): void {
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($entry->lines as $line) {
            $totalDebit += $line->debit;
            $totalCredit += $line->credit;
        }

        if (
            round($totalDebit, 2)
            !==
            round($totalCredit, 2)
        ) {
            throw new RuntimeException(
                'Journal not balanced'
            );
        }
    }

    public function post(
        JournalEntryDTO $entry
    ) {
        $this->validateBalance(
            $entry
        );

        if (
            $entry->reconciliationKey !== null
            &&
            Journal::query()
                ->where(
                    'reconciliation_key',
                    $entry->reconciliationKey
                )
                ->exists()
        ) {
            throw new RuntimeException(
                'Reconciliation journal already posted: '
                .
                $entry->reconciliationKey
            );
        }

        return DB::transaction(
            function () use ($entry) {

                /*
                |--------------------------------------------------------------------------
                | Resolve Accounts Inside Transaction Company
                |--------------------------------------------------------------------------
                |
                | Account codes are unique per company:
                |
                |     (company_id, code)
                |
                | Never resolve an account globally by code.
                |
                | We resolve every journal account before creating the journal
                | header so an invalid account cannot leave a partial journal.
                |
                */

                $resolvedAccounts =
                    [];

                foreach ($entry->lines as $line) {

                    $accountCode =
                        $line->accountCode;

                    if (
                        !array_key_exists(
                            $accountCode,
                            $resolvedAccounts
                        )
                    ) {
                        $account =
                            Account::query()
                                ->where(
                                    'company_id',
                                    $entry->companyId
                                )
                                ->where(
                                    'code',
                                    $accountCode
                                )
                                ->first();

                        if ($account === null) {
                            throw new RuntimeException(
                                sprintf(
                                    'Account %s is not configured for company %d.',
                                    $accountCode,
                                    $entry->companyId
                                )
                            );
                        }

                        if ($account->is_header) {
                            throw new RuntimeException(
                                sprintf(
                                    'Account %s is a header account.',
                                    $accountCode
                                )
                            );
                        }

                        if (!$account->is_active) {
                            throw new RuntimeException(
                                sprintf(
                                    'Account %s is inactive.',
                                    $accountCode
                                )
                            );
                        }

                        $resolvedAccounts[
                            $accountCode
                        ] =
                            $account;
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Create Journal Header
                |--------------------------------------------------------------------------
                */

                $journal =
                    $this
                        ->journalRepository
                        ->create([
                            'company_id' =>
                                $entry->companyId,

                            'journal_date' =>
                                $entry->journalDate
                                    ?? now()->toDateString(),

                            'journal_no' =>
                                $this
                                    ->documentSequenceService
                                    ->next('JV'),

                            'reference_type' =>
                                $entry->referenceType,

                            'reference_id' =>
                                $entry->referenceId,

                            'journal_purpose' =>
                                $entry->journalPurpose,

                            'source_journal_id' =>
                                $entry->sourceJournalId,

                            'reconciliation_key' =>
                                $entry->reconciliationKey,

                            'description' =>
                                $entry->description,

                            'created_by' =>
                                $entry->createdBy,
                        ]);

                /*
                |--------------------------------------------------------------------------
                | Audit Journal Header
                |--------------------------------------------------------------------------
                */

                $this->auditLogService->log(
                    module:
                        'Journal',

                    action:
                        'CREATE',

                    referenceType:
                        Journal::class,

                    referenceId:
                        $journal->id,

                    oldValues:
                        null,

                    newValues: [
                        'journal_no' =>
                            $journal->journal_no,

                        'company_id' =>
                            $journal->company_id,

                        'description' =>
                            $journal->description,

                        'reference_type' =>
                            $journal->reference_type,

                        'reference_id' =>
                            $journal->reference_id,

                        'journal_purpose' =>
                            $journal->journal_purpose,

                        'source_journal_id' =>
                            $journal->source_journal_id,

                        'reconciliation_key' =>
                            $journal->reconciliation_key,
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | Create Journal Details
                |--------------------------------------------------------------------------
                */

                foreach ($entry->lines as $line) {

                    $account =
                        $resolvedAccounts[
                            $line->accountCode
                        ];

                    JournalDetail::create([
                        'journal_id' =>
                            $journal->id,

                        'account_id' =>
                            $account->id,

                        'quantity' =>
                            $line->quantity,

                        'unit_price' =>
                            $line->unitPrice,

                        'debit' =>
                            $line->debit,

                        'credit' =>
                            $line->credit,

                        'description' =>
                            $line->description,
                    ]);
                }

                return $journal->load(
                    'details'
                );
            }
        );
    }
}
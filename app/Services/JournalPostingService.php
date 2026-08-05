<?php

namespace App\Services;

use App\DTO\JournalEntryDTO;
use App\Models\Account;
use App\Models\JournalDetail;
use App\Repositories\Contracts\JournalRepositoryInterface;
use Illuminate\Support\Facades\DB;
use App\Models\Journal;

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
            throw new \Exception(
                'Journal not balanced'
            );
        }
    }

    public function post(
        JournalEntryDTO $entry
    )
    {
        $this->validateBalance($entry);

        return DB::transaction(function () use ($entry) {

            $journal = $this->journalRepository->create([
                'journal_date' => now(),

                'journal_no' => $this
                    ->documentSequenceService
                    ->next('JV'),

                'reference_type' => $entry->referenceType,
                'reference_id' => $entry->referenceId,
                'description' => $entry->description,
                'created_by' => $entry->createdBy,
            ]);

            $this->auditLogService->log(
                module: 'Journal',
                action: 'CREATE',
                referenceType: Journal::class,
                referenceId: $journal->id,
                oldValues: null,
                newValues: [
                    'journal_no' => $journal->journal_no,
                    'description' => $journal->description,
                    'reference_type' => $journal->reference_type,
                    'reference_id' => $journal->reference_id,
                ]
            );

            foreach ($entry->lines as $line) {

                $account = Account::where(
                    'code',
                    $line->accountCode
                )->firstOrFail();

                JournalDetail::create([
                    'journal_id' => $journal->id,
                    'account_id' => $account->id,
                    'quantity'   => $line->quantity,
                    'unit_price' => $line->unitPrice,
                    'debit'      => $line->debit,
                    'credit'     => $line->credit,
                    'description'=> $line->description,
                ]);
            }

            return $journal->load('details');
        });
    }
}
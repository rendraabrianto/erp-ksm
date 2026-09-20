<?php

namespace Tests\Feature;

use App\DTO\JournalEntryDTO;
use App\DTO\JournalLineDTO;
use App\Services\JournalPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class JournalCompanyOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    private JournalPostingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->data =
            InventoryReconciliationTestData::create();

        $this->service =
            app(
                JournalPostingService::class
            );
    }

    public function test_journal_persists_explicit_company_ownership():
        void
    {
        /*
        |--------------------------------------------------------------------------
        | Arrange
        |--------------------------------------------------------------------------
        */

        $companyId =
            $this->data[
                'company_id'
            ];

        /*
        |--------------------------------------------------------------------------
        | Act
        |--------------------------------------------------------------------------
        */

        $journal =
            $this->service->post(
                new JournalEntryDTO(
                    referenceType:
                        'COMPANY_OWNERSHIP_TEST',

                    referenceId:
                        999001,

                    description:
                        'Journal company ownership test',

                    createdBy:
                        $this->data['user_id'],

                    lines: [
                        new JournalLineDTO(
                            accountCode:
                                '1201-T',

                            quantity:
                                0,

                            unitPrice:
                                0,

                            debit:
                                1000,

                            credit:
                                0,

                            description:
                                'Debit ownership test'
                        ),

                        new JournalLineDTO(
                            accountCode:
                                '5001-T',

                            quantity:
                                0,

                            unitPrice:
                                0,

                            debit:
                                0,

                            credit:
                                1000,

                            description:
                                'Credit ownership test'
                        ),
                    ],

                    companyId:
                        $companyId,
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Assert
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $companyId,
            (int) $journal->company_id
        );

        $this->assertDatabaseHas(
            'journals',
            [
                'id' =>
                    $journal->id,

                'company_id' =>
                    $companyId,

                'reference_type' =>
                    'COMPANY_OWNERSHIP_TEST',

                'reference_id' =>
                    999001,
            ]
        );
    }

    public function test_journal_details_inherit_company_through_journal_relation():
        void
    {
        $companyId =
            (int) $this->data['company_id'];

        $service =
            app(
                \App\Services\JournalPostingService::class
            );

        $journal =
            $service->post(
                new \App\DTO\JournalEntryDTO(
                    referenceType:
                        'COMPANY_DETAIL_RELATION_TEST',

                    referenceId:
                        999002,

                    description:
                        'Journal detail company relation test',

                    createdBy:
                        $this->data['user_id'],

                    lines: [
                        new \App\DTO\JournalLineDTO(
                            accountCode:
                                '1201-T',

                            quantity:
                                0.0,

                            unitPrice:
                                0.0,

                            debit:
                                1000.0,

                            credit:
                                0.0,

                            description:
                                'Debit line'
                        ),

                        new \App\DTO\JournalLineDTO(
                            accountCode:
                                '5001-T',

                            quantity:
                                0.0,

                            unitPrice:
                                0.0,

                            debit:
                                0.0,

                            credit:
                                1000.0,

                            description:
                                'Credit line'
                        ),
                    ],

                    companyId:
                        $companyId,
                )
            );

        $journal->load(
            'details.journal'
        );

        $this->assertSame(
            $companyId,
            (int) $journal->company_id
        );

        $this->assertCount(
            2,
            $journal->details
        );

        foreach ($journal->details as $detail) {
            $this->assertSame(
                (int) $journal->id,
                (int) $detail->journal_id
            );

            $this->assertSame(
                $companyId,
                (int) $detail->journal->company_id
            );
        }
    }

    public function test_journal_posting_resolves_duplicate_account_codes_within_entry_company():
        void
    {
        /*
        |--------------------------------------------------------------------------
        | Company A
        |--------------------------------------------------------------------------
        */

        $companyAId =
            (int) $this->data['company_id'];

        $debitAccountA =
            \App\Models\Account::query()
                ->where(
                    'company_id',
                    $companyAId
                )
                ->where(
                    'code',
                    '1201-T'
                )
                ->firstOrFail();

        $creditAccountA =
            \App\Models\Account::query()
                ->where(
                    'company_id',
                    $companyAId
                )
                ->where(
                    'code',
                    '5001-T'
                )
                ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Company B
        |--------------------------------------------------------------------------
        */

        $companyBId =
            \Illuminate\Support\Facades\DB::table(
                'companies'
            )->insertGetId([
                'code' =>
                    'COMP-JRN-B',

                'name' =>
                    'Company B Journal Isolation',

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
        | Company B Account Groups
        |--------------------------------------------------------------------------
        */

        $assetGroupBId =
            \Illuminate\Support\Facades\DB::table(
                'account_groups'
            )->insertGetId([
                'company_id' =>
                    $companyBId,

                'code' =>
                    'AST-JRNB',

                'name' =>
                    'Asset Company B Journal',

                'is_active' =>
                    true,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);

        $expenseGroupBId =
            \Illuminate\Support\Facades\DB::table(
                'account_groups'
            )->insertGetId([
                'company_id' =>
                    $companyBId,

                'code' =>
                    'EXP-JRNB',

                'name' =>
                    'Expense Company B Journal',

                'is_active' =>
                    true,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);

        /*
        |--------------------------------------------------------------------------
        | Company B — SAME Account Codes
        |--------------------------------------------------------------------------
        |
        | Ini inti attack test.
        |
        | Company A:
        |   1201-T
        |   5001-T
        |
        | Company B:
        |   1201-T
        |   5001-T
        |
        | Kode sengaja IDENTIK.
        |--------------------------------------------------------------------------
        */

        $debitAccountBId =
            \Illuminate\Support\Facades\DB::table(
                'accounts'
            )->insertGetId([
                'company_id' =>
                    $companyBId,

                'account_group_id' =>
                    $assetGroupBId,

                'code' =>
                    '1201-T',

                'name' =>
                    'Inventory Company B Duplicate Code',

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

        $creditAccountBId =
            \Illuminate\Support\Facades\DB::table(
                'accounts'
            )->insertGetId([
                'company_id' =>
                    $companyBId,

                'account_group_id' =>
                    $expenseGroupBId,

                'code' =>
                    '5001-T',

                'name' =>
                    'COGS Company B Duplicate Code',

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
        | Precondition
        |--------------------------------------------------------------------------
        |
        | Pastikan test benar-benar memiliki duplicate account code.
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            2,
            \App\Models\Account::query()
                ->where(
                    'code',
                    '1201-T'
                )
                ->count()
        );

        $this->assertSame(
            2,
            \App\Models\Account::query()
                ->where(
                    'code',
                    '5001-T'
                )
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Post Journal For Company A
        |--------------------------------------------------------------------------
        */

        $journal =
            $this->service->post(
                new JournalEntryDTO(
                    referenceType:
                        'COMPANY_ACCOUNT_ISOLATION_TEST',

                    referenceId:
                        999003,

                    description:
                        'Duplicate account code company isolation test',

                    createdBy:
                        $this->data['user_id'],

                    lines: [
                        new JournalLineDTO(
                            accountCode:
                                '1201-T',

                            quantity:
                                0.0,

                            unitPrice:
                                0.0,

                            debit:
                                1000.0,

                            credit:
                                0.0,

                            description:
                                'Company A debit'
                        ),

                        new JournalLineDTO(
                            accountCode:
                                '5001-T',

                            quantity:
                                0.0,

                            unitPrice:
                                0.0,

                            debit:
                                0.0,

                            credit:
                                1000.0,

                            description:
                                'Company A credit'
                        ),
                    ],

                    companyId:
                        $companyAId,
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Journal Header Must Belong To Company A
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $companyAId,
            (int) $journal->company_id
        );

        /*
        |--------------------------------------------------------------------------
        | Journal Details Must Use Company A Accounts
        |--------------------------------------------------------------------------
        */

        $journal->load(
            'details'
        );

        $this->assertCount(
            2,
            $journal->details
        );

        $debitDetail =
            $journal->details
                ->firstWhere(
                    'account_id',
                    $debitAccountA->id
                );

        $creditDetail =
            $journal->details
                ->firstWhere(
                    'account_id',
                    $creditAccountA->id
                );

        $this->assertNotNull(
            $debitDetail,
            'Journal debit must resolve the account from Company A.'
        );

        $this->assertNotNull(
            $creditDetail,
            'Journal credit must resolve the account from Company A.'
        );

        /*
        |--------------------------------------------------------------------------
        | Company B Accounts Must Never Be Used
        |--------------------------------------------------------------------------
        */

        $this->assertFalse(
            $journal->details
                ->contains(
                    'account_id',
                    $debitAccountBId
                )
        );

        $this->assertFalse(
            $journal->details
                ->contains(
                    'account_id',
                    $creditAccountBId
                )
        );

        /*
        |--------------------------------------------------------------------------
        | Strong Database Assertions
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseHas(
            'journal_details',
            [
                'journal_id' =>
                    $journal->id,

                'account_id' =>
                    $debitAccountA->id,

                'debit' =>
                    1000.0000,
            ]
        );

        $this->assertDatabaseHas(
            'journal_details',
            [
                'journal_id' =>
                    $journal->id,

                'account_id' =>
                    $creditAccountA->id,

                'credit' =>
                    1000.0000,
            ]
        );

        $this->assertDatabaseMissing(
            'journal_details',
            [
                'journal_id' =>
                    $journal->id,

                'account_id' =>
                    $debitAccountBId,
            ]
        );

        $this->assertDatabaseMissing(
            'journal_details',
            [
                'journal_id' =>
                    $journal->id,

                'account_id' =>
                    $creditAccountBId,
            ]
        );
    }
}
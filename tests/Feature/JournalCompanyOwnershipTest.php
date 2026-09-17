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
}
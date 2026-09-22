<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DocumentSequence;
use App\Services\DocumentSequenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentSequenceCompanyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private DocumentSequenceService $service;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service =
            app(DocumentSequenceService::class);

        $this->companyA =
            Company::query()
                ->create([
                    'code' =>
                        'SEQ-COMP-A',

                    'name' =>
                        'Sequence Company A',

                    'phone' =>
                        null,

                    'email' =>
                        null,

                    'address' =>
                        null,

                    'is_active' =>
                        true,
                ]);

        $this->companyB =
            Company::query()
                ->create([
                    'code' =>
                        'SEQ-COMP-B',

                    'name' =>
                        'Sequence Company B',

                    'phone' =>
                        null,

                    'email' =>
                        null,

                    'address' =>
                        null,

                    'is_active' =>
                        true,
                ]);

        DocumentSequence::query()
            ->create([
                'company_id' =>
                    $this->companyA->id,

                'document_type' =>
                    'TRF',

                'prefix' =>
                    'TRF',

                'description' =>
                    'Company A Transfer',

                'current_number' =>
                    0,

                'padding' =>
                    5,

                'is_active' =>
                    true,
            ]);

        DocumentSequence::query()
            ->create([
                'company_id' =>
                    $this->companyB->id,

                'document_type' =>
                    'TRF',

                'prefix' =>
                    'TRF',

                'description' =>
                    'Company B Transfer',

                'current_number' =>
                    0,

                'padding' =>
                    5,

                'is_active' =>
                    true,
            ]);
    }

    public function test_same_document_type_can_exist_for_different_companies():
        void
    {
        $this->assertDatabaseHas(
            'document_sequences',
            [
                'company_id' =>
                    $this->companyA->id,

                'document_type' =>
                    'TRF',

                'current_number' =>
                    0,
            ]
        );

        $this->assertDatabaseHas(
            'document_sequences',
            [
                'company_id' =>
                    $this->companyB->id,

                'document_type' =>
                    'TRF',

                'current_number' =>
                    0,
            ]
        );

        $this->assertSame(
            2,
            DocumentSequence::query()
                ->where(
                    'document_type',
                    'TRF'
                )
                ->count()
        );
    }

    public function test_company_a_increment_does_not_modify_company_b():
        void
    {
        $numberA =
            $this->service->next(
                $this->companyA->id,
                'TRF'
            );

        $this->assertStringStartsWith(
            'TRF-' . now()->format('Ymd') . '-',
            $numberA
        );

        $this->assertStringEndsWith(
            '-00001',
            $numberA
        );

        $this->assertSame(
            1,
            (int) DocumentSequence::query()
                ->where(
                    'company_id',
                    $this->companyA->id
                )
                ->where(
                    'document_type',
                    'TRF'
                )
                ->value(
                    'current_number'
                )
        );

        $this->assertSame(
            0,
            (int) DocumentSequence::query()
                ->where(
                    'company_id',
                    $this->companyB->id
                )
                ->where(
                    'document_type',
                    'TRF'
                )
                ->value(
                    'current_number'
                )
        );
    }

    public function test_each_company_has_independent_counter():
        void
    {
        $numberA1 =
            $this->service->next(
                $this->companyA->id,
                'TRF'
            );

        $numberA2 =
            $this->service->next(
                $this->companyA->id,
                'TRF'
            );

        $numberB1 =
            $this->service->next(
                $this->companyB->id,
                'TRF'
            );

        $this->assertStringEndsWith(
            '-00001',
            $numberA1
        );

        $this->assertStringEndsWith(
            '-00002',
            $numberA2
        );

        $this->assertStringEndsWith(
            '-00001',
            $numberB1
        );

        $this->assertSame(
            2,
            (int) DocumentSequence::query()
                ->where(
                    'company_id',
                    $this->companyA->id
                )
                ->where(
                    'document_type',
                    'TRF'
                )
                ->value(
                    'current_number'
                )
        );

        $this->assertSame(
            1,
            (int) DocumentSequence::query()
                ->where(
                    'company_id',
                    $this->companyB->id
                )
                ->where(
                    'document_type',
                    'TRF'
                )
                ->value(
                    'current_number'
                )
        );
    }

    public function test_missing_sequence_is_resolved_within_requested_company():
        void
    {
        DocumentSequence::query()
            ->where(
                'company_id',
                $this->companyB->id
            )
            ->where(
                'document_type',
                'TRF'
            )
            ->delete();

        $this->expectException(
            \RuntimeException::class
        );

        $this->expectExceptionMessage(
            "Document sequence not found for company {$this->companyB->id}: TRF"
        );

        $this->service->next(
            $this->companyB->id,
            'TRF'
        );
    }

    public function test_inactive_sequence_is_rejected_only_for_requested_company():
        void
    {
        DocumentSequence::query()
            ->where(
                'company_id',
                $this->companyB->id
            )
            ->where(
                'document_type',
                'TRF'
            )
            ->update([
                'is_active' =>
                    false,
            ]);

        $numberA =
            $this->service->next(
                $this->companyA->id,
                'TRF'
            );

        $this->assertStringEndsWith(
            '-00001',
            $numberA
        );

        try {
            $this->service->next(
                $this->companyB->id,
                'TRF'
            );

            $this->fail(
                'Expected inactive Company B sequence to be rejected.'
            );
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                "Document sequence is inactive for company {$this->companyB->id}: TRF",
                $exception->getMessage()
            );
        }

        $this->assertSame(
            1,
            (int) DocumentSequence::query()
                ->where(
                    'company_id',
                    $this->companyA->id
                )
                ->where(
                    'document_type',
                    'TRF'
                )
                ->value(
                    'current_number'
                )
        );

        $this->assertSame(
            0,
            (int) DocumentSequence::query()
                ->where(
                    'company_id',
                    $this->companyB->id
                )
                ->where(
                    'document_type',
                    'TRF'
                )
                ->value(
                    'current_number'
                )
        );
    }
}
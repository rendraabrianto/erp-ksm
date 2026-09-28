<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PurchaseRequest;
use App\Repositories\Contracts\PurchaseRequestRepositoryInterface;
use App\Services\PurchaseRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class PurchaseRequestCompanyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    private Company $companyA;

    private Company $companyB;

    private PurchaseRequestRepositoryInterface $repository;

    private PurchaseRequestService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->data =
            InventoryReconciliationTestData::create();

        $this->companyA =
            Company::query()->findOrFail(
                $this->data['company_id']
            );

        $this->companyB =
            Company::query()->create([
                'code' => 'PR-COMP-B',
                'name' => 'Purchase Request Company B',
                'is_active' => true,
            ]);

        $this->repository =
            app(
                PurchaseRequestRepositoryInterface::class
            );

        $this->service =
            app(PurchaseRequestService::class);
    }

    public function test_repository_paginate_only_returns_purchase_requests_from_requested_company(): void
    {
        $companyAPurchaseRequest =
            $this->createPurchaseRequest(
                $this->companyA->id,
                'PR-A-001'
            );

        $companyBPurchaseRequest =
            $this->createPurchaseRequest(
                $this->companyB->id,
                'PR-B-001'
            );

        $result =
            $this->repository->paginate(
                $this->companyA->id,
                50
            );

        $ids =
            collect($result->items())
                ->pluck('id')
                ->all();

        $this->assertContains(
            $companyAPurchaseRequest->id,
            $ids
        );

        $this->assertNotContains(
            $companyBPurchaseRequest->id,
            $ids
        );
    }

    public function test_repository_find_cannot_read_purchase_request_from_another_company(): void
    {
        $companyBPurchaseRequest =
            $this->createPurchaseRequest(
                $this->companyB->id,
                'PR-B-002'
            );

        $result =
            $this->repository->find(
                $this->companyA->id,
                $companyBPurchaseRequest->id
            );

        $this->assertNull($result);
    }

    public function test_repository_find_can_read_purchase_request_from_requested_company(): void
    {
        $companyAPurchaseRequest =
            $this->createPurchaseRequest(
                $this->companyA->id,
                'PR-A-002'
            );

        $result =
            $this->repository->find(
                $this->companyA->id,
                $companyAPurchaseRequest->id
            );

        $this->assertNotNull($result);

        $this->assertSame(
            $companyAPurchaseRequest->id,
            $result->id
        );

        $this->assertSame(
            $this->companyA->id,
            $result->company_id
        );
    }

    public function test_service_paginate_only_returns_purchase_requests_from_requested_company(): void
    {
        $companyAPurchaseRequest =
            $this->createPurchaseRequest(
                $this->companyA->id,
                'PR-A-003'
            );

        $companyBPurchaseRequest =
            $this->createPurchaseRequest(
                $this->companyB->id,
                'PR-B-003'
            );

        $result =
            $this->service->paginate(
                $this->companyA->id,
                50
            );

        $ids =
            collect($result->items())
                ->pluck('id')
                ->all();

        $this->assertContains(
            $companyAPurchaseRequest->id,
            $ids
        );

        $this->assertNotContains(
            $companyBPurchaseRequest->id,
            $ids
        );
    }

    private function createPurchaseRequest(
        int $companyId,
        string $number
    ): PurchaseRequest {
        return PurchaseRequest::query()->create([
            'company_id' => $companyId,
            'pr_no' => $number,
            'warehouse_id' => $this->data['warehouse_id'],
            'pr_date' => '2026-09-27',
            'status' => 'DRAFT',
            'remarks' => 'Company isolation test',
            'created_by' => $this->data['user_id'],
        ]);
    }
}
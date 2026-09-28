<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Services\BranchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchCompanyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;
    private Company $companyB;

    private Branch $branchA;
    private Branch $branchB;

    private BranchRepositoryInterface $repository;
    private BranchService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::create([
            'code' => 'CMP-A',
            'name' => 'Company A',
            'is_active' => true,
        ]);

        $this->companyB = Company::create([
            'code' => 'CMP-B',
            'name' => 'Company B',
            'is_active' => true,
        ]);

        $this->branchA = Branch::create([
            'company_id' => $this->companyA->id,
            'code' => 'BR-A',
            'name' => 'Branch A',
            'is_active' => true,
        ]);

        $this->branchB = Branch::create([
            'company_id' => $this->companyB->id,
            'code' => 'BR-B',
            'name' => 'Branch B',
            'is_active' => true,
        ]);

        $this->repository = app(
            BranchRepositoryInterface::class
        );

        $this->service = app(
            BranchService::class
        );
    }

    public function test_company_can_only_paginate_its_own_branches(): void
    {
        $branches = $this->service->paginate(
            $this->companyA->id,
            10
        );

        $this->assertCount(1, $branches->items());

        $this->assertSame(
            $this->branchA->id,
            $branches->items()[0]->id
        );

        $this->assertFalse(
            collect($branches->items())
                ->contains(
                    fn (Branch $branch) =>
                        $branch->id === $this->branchB->id
                )
        );
    }

    public function test_company_cannot_find_branch_owned_by_another_company(): void
    {
        $branch = $this->service->find(
            $this->companyA->id,
            $this->branchB->id
        );

        $this->assertNull($branch);

        $ownBranch = $this->service->find(
            $this->companyA->id,
            $this->branchA->id
        );

        $this->assertNotNull($ownBranch);
        $this->assertSame(
            $this->branchA->id,
            $ownBranch->id
        );
    }

    public function test_company_cannot_update_branch_owned_by_another_company(): void
    {
        $updated = $this->service->update(
            $this->companyA->id,
            $this->branchB,
            [
                'name' => 'Compromised Branch',
            ]
        );

        $this->assertFalse($updated);

        $this->branchB->refresh();

        $this->assertSame(
            'Branch B',
            $this->branchB->name
        );

        $this->assertSame(
            $this->companyB->id,
            $this->branchB->company_id
        );
    }

    public function test_branch_company_ownership_is_immutable_during_update(): void
    {
        $updated = $this->service->update(
            $this->companyA->id,
            $this->branchA,
            [
                'name' => 'Branch A Updated',
                'company_id' => $this->companyB->id,
            ]
        );

        $this->assertTrue($updated);

        $this->branchA->refresh();

        $this->assertSame(
            'Branch A Updated',
            $this->branchA->name
        );

        $this->assertSame(
            $this->companyA->id,
            $this->branchA->company_id
        );
    }

    public function test_repository_create_forces_branch_company_from_trusted_context(): void
    {
        $branch = $this->repository->create(
            $this->companyA->id,
            [
                'company_id' => $this->companyB->id,
                'code' => 'BR-FORCED',
                'name' => 'Forced Company Branch',
                'is_active' => true,
            ]
        );

        $this->assertSame(
            $this->companyA->id,
            $branch->company_id
        );

        $this->assertDatabaseHas(
            'branches',
            [
                'id' => $branch->id,
                'company_id' => $this->companyA->id,
                'code' => 'BR-FORCED',
            ]
        );

        $this->assertDatabaseMissing(
            'branches',
            [
                'id' => $branch->id,
                'company_id' => $this->companyB->id,
            ]
        );
    }
}
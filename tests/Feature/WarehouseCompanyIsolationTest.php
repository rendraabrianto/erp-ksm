<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Warehouse;
use App\Repositories\Contracts\WarehouseRepositoryInterface;
use App\Services\WarehouseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;


class WarehouseCompanyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;
    private Company $companyB;

    private Branch $branchA;
    private Branch $branchB;

    private WarehouseRepositoryInterface $repository;
    private WarehouseService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::create([
            'code' => 'COMP-A',
            'name' => 'Company A',
            'is_active' => true,
        ]);

        $this->companyB = Company::create([
            'code' => 'COMP-B',
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
            WarehouseRepositoryInterface::class
        );

        $this->service = app(
            WarehouseService::class
        );
    }

    public function test_same_warehouse_code_can_exist_in_different_companies(): void
    {
        $warehouseA = $this->service->create(
            $this->companyA->id,
            [
                'branch_id' => $this->branchA->id,
                'code' => 'WH-001',
                'name' => 'Warehouse A',
            ]
        );

        $warehouseB = $this->service->create(
            $this->companyB->id,
            [
                'branch_id' => $this->branchB->id,
                'code' => 'WH-001',
                'name' => 'Warehouse B',
            ]
        );

        $this->assertSame(
            'WH-001',
            $warehouseA->code
        );

        $this->assertSame(
            'WH-001',
            $warehouseB->code
        );

        $this->assertNotSame(
            $warehouseA->company_id,
            $warehouseB->company_id
        );

        $this->assertDatabaseHas('warehouses', [
            'company_id' => $this->companyA->id,
            'code' => 'WH-001',
        ]);

        $this->assertDatabaseHas('warehouses', [
            'company_id' => $this->companyB->id,
            'code' => 'WH-001',
        ]);
    }

    public function test_duplicate_warehouse_code_inside_same_company_is_rejected(): void
    {
        $this->service->create(
            $this->companyA->id,
            [
                'branch_id' => $this->branchA->id,
                'code' => 'WH-001',
                'name' => 'Warehouse A1',
            ]
        );

        $this->expectException(
            \Illuminate\Database\QueryException::class
        );

        $this->service->create(
            $this->companyA->id,
            [
                'branch_id' => $this->branchA->id,
                'code' => 'WH-001',
                'name' => 'Warehouse A2',
            ]
        );
    }

    public function test_repository_reads_are_company_scoped(): void
    {
        $warehouseA = $this->createWarehouse(
            $this->companyA,
            $this->branchA,
            'WH-A'
        );

        $warehouseB = $this->createWarehouse(
            $this->companyB,
            $this->branchB,
            'WH-B'
        );

        $companyAResult = $this->repository->paginate(
            $this->companyA->id,
            10
        );

        $ids = collect(
            $companyAResult->items()
        )->pluck('id');

        $this->assertTrue(
            $ids->contains($warehouseA->id)
        );

        $this->assertFalse(
            $ids->contains($warehouseB->id)
        );

        $this->assertNull(
            $this->repository->find(
                $warehouseB->id,
                $this->companyA->id
            )
        );
    }

    public function test_cross_company_update_is_rejected_by_repository_scope(): void
    {
        $warehouseB = $this->createWarehouse(
            $this->companyB,
            $this->branchB,
            'WH-B'
        );

        $result = $this->repository->update(
            $warehouseB->id,
            $this->companyA->id,
            [
                'name' => 'Hacked Warehouse',
            ]
        );

        $this->assertFalse($result);

        $this->assertDatabaseHas('warehouses', [
            'id' => $warehouseB->id,
            'company_id' => $this->companyB->id,
            'name' => 'Warehouse COMP-B',
        ]);
    }

    public function test_cross_company_delete_is_rejected_by_repository_scope(): void
    {
        $warehouseB = $this->createWarehouse(
            $this->companyB,
            $this->branchB,
            'WH-B'
        );

        $result = $this->repository->delete(
            $warehouseB->id,
            $this->companyA->id
        );

        $this->assertFalse($result);

        $this->assertDatabaseHas('warehouses', [
            'id' => $warehouseB->id,
            'company_id' => $this->companyB->id,
            'deleted_at' => null,
        ]);
    }

    public function test_cross_company_branch_is_rejected_when_creating_warehouse(): void
    {
        $this->expectException(RuntimeException::class);

        $this->expectExceptionMessage(
            'Branch does not belong to warehouse company.'
        );

        $this->service->create(
            $this->companyA->id,
            [
                'branch_id' => $this->branchB->id,
                'code' => 'WH-CROSS',
                'name' => 'Cross Company Warehouse',
            ]
        );
    }

    public function test_cross_company_branch_is_rejected_when_updating_warehouse(): void
    {
        $warehouseA = $this->createWarehouse(
            $this->companyA,
            $this->branchA,
            'WH-A'
        );

        try {
            $this->service->update(
                $warehouseA->id,
                $this->companyA->id,
                [
                    'branch_id' => $this->branchB->id,
                ]
            );

            $this->fail(
                'Expected RuntimeException was not thrown.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Branch does not belong to warehouse company.',
                $exception->getMessage()
            );
        }

        $warehouseA->refresh();

        $this->assertSame(
            $this->branchA->id,
            $warehouseA->branch_id
        );

        $this->assertSame(
            $this->companyA->id,
            $warehouseA->company_id
        );
    }

    public function test_company_ownership_cannot_be_changed_through_update_payload(): void
    {
        $warehouseA = $this->createWarehouse(
            $this->companyA,
            $this->branchA,
            'WH-A'
        );

        $result = $this->service->update(
            $warehouseA->id,
            $this->companyA->id,
            [
                'company_id' => $this->companyB->id,
                'name' => 'Warehouse A Updated',
            ]
        );

        $this->assertTrue($result);

        $warehouseA->refresh();

        $this->assertSame(
            $this->companyA->id,
            $warehouseA->company_id
        );

        $this->assertSame(
            'Warehouse A Updated',
            $warehouseA->name
        );
    }

    private function createWarehouse(
        Company $company,
        Branch $branch,
        string $code
    ): Warehouse {
        return Warehouse::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'code' => $code,
            'name' => sprintf(
                'Warehouse %s',
                $company->code
            ),
            'is_active' => true,
        ]);
    }
}
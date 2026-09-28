<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardCompanyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;
    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::create([
            'code' => 'DASH-A',
            'name' => 'Dashboard Company A',
            'is_active' => true,
        ]);

        $this->companyB = Company::create([
            'code' => 'DASH-B',
            'name' => 'Dashboard Company B',
            'is_active' => true,
        ]);

        Branch::create([
            'company_id' => $this->companyA->id,
            'code' => 'A-01',
            'name' => 'Company A Branch 1',
            'is_active' => true,
        ]);

        Branch::create([
            'company_id' => $this->companyA->id,
            'code' => 'A-02',
            'name' => 'Company A Branch 2',
            'is_active' => true,
        ]);

        Branch::create([
            'company_id' => $this->companyB->id,
            'code' => 'B-01',
            'name' => 'Company B Branch 1',
            'is_active' => true,
        ]);

        User::create([
            'company_id' => $this->companyA->id,
            'name' => 'Company A User 1',
            'email' => 'dash-a1@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        User::create([
            'company_id' => $this->companyA->id,
            'name' => 'Company A User 2',
            'email' => 'dash-a2@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        User::create([
            'company_id' => $this->companyB->id,
            'name' => 'Company B User 1',
            'email' => 'dash-b1@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
    }

    public function test_repository_statistics_are_isolated_by_company(): void
    {
        /** @var DashboardRepositoryInterface $repository */
        $repository = app(
            DashboardRepositoryInterface::class
        );

        $companyA = $repository->getStatistics(
            $this->companyA->id
        );

        $companyB = $repository->getStatistics(
            $this->companyB->id
        );

        $this->assertSame(1, $companyA['companies']);
        $this->assertSame(2, $companyA['branches']);
        $this->assertSame(2, $companyA['users']);

        $this->assertSame(1, $companyB['companies']);
        $this->assertSame(1, $companyB['branches']);
        $this->assertSame(1, $companyB['users']);

        /*
         * Roles are intentionally global authorization configuration.
         */
        $this->assertSame(
            $companyA['roles'],
            $companyB['roles']
        );
    }

    public function test_service_returns_company_scoped_dashboard_data(): void
    {
        /** @var DashboardService $service */
        $service = app(DashboardService::class);

        $dashboardA = $service->getDashboardData(
            $this->companyA->id
        );

        $dashboardB = $service->getDashboardData(
            $this->companyB->id
        );

        $this->assertSame(1, $dashboardA->companies);
        $this->assertSame(2, $dashboardA->branches);
        $this->assertSame(2, $dashboardA->users);

        $this->assertSame(1, $dashboardB->companies);
        $this->assertSame(1, $dashboardB->branches);
        $this->assertSame(1, $dashboardB->users);
    }

    public function test_authenticated_dashboard_uses_users_company_context(): void
    {
        $userA = User::query()
            ->where('company_id', $this->companyA->id)
            ->firstOrFail();

        $response = $this
            ->actingAs($userA)
            ->get(route('dashboard'));

        $response->assertOk();

        $response->assertViewHas(
            'dashboard',
            function ($dashboard): bool {
                return $dashboard->companies === 1
                    && $dashboard->branches === 2
                    && $dashboard->users === 2;
            }
        );
    }
}
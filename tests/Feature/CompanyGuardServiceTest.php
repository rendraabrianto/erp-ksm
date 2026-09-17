<?php

namespace Tests\Feature;

use App\Services\CompanyGuardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class CompanyGuardServiceTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    private CompanyGuardService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->data =
            InventoryReconciliationTestData::create();

        $this->service =
            app(
                CompanyGuardService::class
            );
    }

    public function test_actor_from_same_company_is_allowed(): void
    {
        $user =
            $this->service
                ->assertActorBelongsToCompany(
                    $this->data['user_id'],
                    $this->data['company_id']
                );

        $this->assertSame(
            $this->data['user_id'],
            (int) $user->id
        );

        $this->assertSame(
            $this->data['company_id'],
            (int) $user->company_id
        );
    }

    public function test_actor_from_different_company_is_rejected(): void
    {
        $companyBId =
            DB::table('companies')
                ->insertGetId([
                    'code' =>
                        'COMP-GUARD-B',

                    'name' =>
                        'Company Guard B',

                    'is_active' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        $branchBId =
            DB::table('branches')
                ->insertGetId([
                    'company_id' =>
                        $companyBId,

                    'code' =>
                        'BR-GUARD-B',

                    'name' =>
                        'Branch Guard B',

                    'is_active' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        $userBId =
            DB::table('users')
                ->insertGetId([
                    'company_id' =>
                        $companyBId,

                    'branch_id' =>
                        $branchBId,

                    'name' =>
                        'User Guard B',

                    'email' =>
                        'guard-b@example.test',

                    'password' =>
                        Hash::make('password'),

                    'is_active' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Actor does not belong to transaction company.'
        );

        $this->service
            ->assertActorBelongsToCompany(
                $userBId,
                $this->data['company_id']
            );
    }
}
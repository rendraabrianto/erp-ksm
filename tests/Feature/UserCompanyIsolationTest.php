<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserCompanyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;
    private Company $companyB;

    private Branch $branchA;
    private Branch $branchB;

    private User $userA;
    private User $userB;

    private UserRepositoryInterface $repository;
    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::create([
            'code' => 'USR-A',
            'name' => 'User Company A',
            'is_active' => true,
        ]);

        $this->companyB = Company::create([
            'code' => 'USR-B',
            'name' => 'User Company B',
            'is_active' => true,
        ]);

        $this->branchA = Branch::create([
            'company_id' => $this->companyA->id,
            'code' => 'BR-USR-A',
            'name' => 'User Branch A',
            'is_active' => true,
        ]);

        $this->branchB = Branch::create([
            'company_id' => $this->companyB->id,
            'code' => 'BR-USR-B',
            'name' => 'User Branch B',
            'is_active' => true,
        ]);

        $this->userA = User::create([
            'name' => 'User A',
            'email' => 'user-a@example.test',
            'password' => 'password',
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'is_active' => true,
        ]);

        $this->userB = User::create([
            'name' => 'User B',
            'email' => 'user-b@example.test',
            'password' => 'password',
            'company_id' => $this->companyB->id,
            'branch_id' => $this->branchB->id,
            'is_active' => true,
        ]);

        Role::findOrCreate(
            'Branch Manager',
            'web'
        );

        $this->repository = app(
            UserRepositoryInterface::class
        );

        $this->service = app(
            UserService::class
        );
    }

    public function test_repository_paginate_only_returns_users_from_requested_company(): void
    {
        $users = $this->repository->paginate(
            $this->companyA->id,
            50
        );

        $ids = collect($users->items())
            ->pluck('id')
            ->all();

        $this->assertContains(
            $this->userA->id,
            $ids
        );

        $this->assertNotContains(
            $this->userB->id,
            $ids
        );
    }

    public function test_repository_cannot_find_user_from_another_company(): void
    {
        $user = $this->repository->find(
            $this->companyA->id,
            $this->userB->id
        );

        $this->assertNull($user);
    }

    public function test_repository_cannot_update_user_from_another_company(): void
    {
        $updated = $this->repository->update(
            $this->companyA->id,
            $this->userB,
            [
                'name' => 'Compromised User',
            ]
        );

        $this->assertFalse($updated);

        $this->assertSame(
            'User B',
            $this->userB->fresh()->name
        );
    }

    public function test_repository_cannot_delete_user_from_another_company(): void
    {
        $deleted = $this->repository->delete(
            $this->companyA->id,
            $this->userB
        );

        $this->assertFalse($deleted);

        $this->assertDatabaseHas('users', [
            'id' => $this->userB->id,
            'company_id' => $this->companyB->id,
        ]);
    }

    public function test_repository_create_forces_transaction_company(): void
    {
        $user = $this->repository->create(
            $this->companyA->id,
            [
                'name' => 'Forced Company User',
                'email' => 'forced-company@example.test',
                'password' => 'password',
                'company_id' => $this->companyB->id,
                'branch_id' => $this->branchA->id,
                'is_active' => true,
            ]
        );

        $this->assertSame(
            $this->companyA->id,
            (int) $user->company_id
        );

        $this->assertNotSame(
            $this->companyB->id,
            (int) $user->company_id
        );
    }

    public function test_service_rejects_branch_from_another_company(): void
    {
        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Branch does not belong to user company.'
        );

        $this->service->create(
            $this->companyA->id,
            [
                'name' => 'Cross Company Branch',
                'email' => 'cross-branch@example.test',
                'password' => 'password',
                'branch_id' => $this->branchB->id,
                'role' => 'Branch Manager',
                'is_active' => true,
            ]
        );
    }

    public function test_service_create_assigns_authenticated_company_and_valid_branch(): void
    {
        $user = $this->service->create(
            $this->companyA->id,
            [
                'name' => 'Company A New User',
                'email' => 'company-a-new@example.test',
                'password' => 'password',
                'company_id' => $this->companyB->id,
                'branch_id' => $this->branchA->id,
                'role' => 'Branch Manager',
                'is_active' => true,
            ]
        );

        $this->assertSame(
            $this->companyA->id,
            (int) $user->company_id
        );

        $this->assertSame(
            $this->branchA->id,
            (int) $user->branch_id
        );

        $this->assertTrue(
            $user->hasRole('Branch Manager')
        );
    }

    public function test_http_store_rejects_company_id_from_payload(): void
    {
        $response = $this
            ->actingAs($this->userA)
            ->post(route('users.store'), [
                'name' => 'Payload Company User',
                'email' => 'payload-company@example.test',
                'password' => 'password',
                'company_id' => $this->companyB->id,
                'branch_id' => $this->branchA->id,
                'role' => 'Branch Manager',
                'is_active' => true,
            ]);

        $response->assertSessionHasErrors([
            'company_id',
        ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'payload-company@example.test',
        ]);
    }

    public function test_http_store_rejects_branch_from_another_company(): void
    {
        $response = $this
            ->actingAs($this->userA)
            ->post(route('users.store'), [
                'name' => 'Foreign Branch User',
                'email' => 'foreign-branch@example.test',
                'password' => 'password',
                'branch_id' => $this->branchB->id,
                'role' => 'Branch Manager',
                'is_active' => true,
            ]);

        $response->assertSessionHasErrors([
            'branch_id',
        ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'foreign-branch@example.test',
        ]);
    }

    public function test_http_index_only_displays_authenticated_company_users(): void
    {
        $response = $this
            ->actingAs($this->userA)
            ->get(route('users.index'));

        $response->assertOk();

        $response->assertViewHas(
            'users',
            function ($users): bool {
                $ids = collect($users->items())
                    ->pluck('id')
                    ->all();

                return in_array(
                    $this->userA->id,
                    $ids,
                    true
                ) && ! in_array(
                    $this->userB->id,
                    $ids,
                    true
                );
            }
        );
    }
}
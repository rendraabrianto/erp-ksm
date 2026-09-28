<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserService
{
    public function __construct(
        private UserRepositoryInterface $repository
    ) {}

    public function create(
        int $companyId,
        array $data
    ): User {
        $branchId = $data['branch_id'] ?? null;

        if ($branchId !== null) {
            $branchExists = Branch::query()
                ->where('company_id', $companyId)
                ->whereKey($branchId)
                ->exists();

            if (! $branchExists) {
                throw new RuntimeException(
                    'Branch does not belong to user company.'
                );
            }
        }

        $user = $this->repository->create(
            $companyId,
            [
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'branch_id' => $branchId,
                'is_active' => isset($data['is_active']),
            ]
        );

        $user->assignRole($data['role']);

        return $user;
    }

    public function paginate(
        int $companyId,
        int $perPage = 10
    ) {
        return $this->repository->paginate(
            $companyId,
            $perPage
        );
    }

    public function find(
        int $companyId,
        int $id
    ): ?User {
        return $this->repository->find(
            $companyId,
            $id
        );
    }

    public function update(
        int $companyId,
        User $user,
        array $data
    ): bool {
        if (array_key_exists('branch_id', $data)) {
            $branchId = $data['branch_id'];

            if ($branchId !== null) {
                $branchExists = Branch::query()
                    ->where('company_id', $companyId)
                    ->whereKey($branchId)
                    ->exists();

                if (! $branchExists) {
                    throw new RuntimeException(
                        'Branch does not belong to user company.'
                    );
                }
            }
        }

        unset($data['company_id']);

        return $this->repository->update(
            $companyId,
            $user,
            $data
        );
    }

    public function delete(
        int $companyId,
        User $user
    ): bool {
        return $this->repository->delete(
            $companyId,
            $user
        );
    }
}
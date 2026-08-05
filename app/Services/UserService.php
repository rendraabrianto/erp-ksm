<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Repositories\Contracts\UserRepositoryInterface;

class UserService
{
    public function __construct(
        private UserRepositoryInterface $repository
    ) {}

    public function create(array $data): User
    {
        $user = $this->repository->create([
            'name'       => $data['name'],
            'email'      => $data['email'],
            'password'   => Hash::make($data['password']),
            'company_id' => $data['company_id'],
            'branch_id'  => $data['branch_id'],
            'is_active'  => isset($data['is_active']),
        ]);

        $user->assignRole($data['role']);

        return $user;
    }

    public function paginate()
    {
        return $this->repository->paginate();
    }
}
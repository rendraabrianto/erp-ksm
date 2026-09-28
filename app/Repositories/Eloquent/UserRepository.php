<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface
{
    public function paginate(
        int $companyId,
        int $perPage = 10
    ) {
        return User::query()
            ->where('company_id', $companyId)
            ->with([
                'company',
                'branch',
                'roles',
            ])
            ->latest()
            ->paginate($perPage);
    }

    public function find(
        int $companyId,
        int $id
    ): ?User {
        return User::query()
            ->where('company_id', $companyId)
            ->whereKey($id)
            ->first();
    }

    public function create(
        int $companyId,
        array $data
    ): User {
        /*
         * Company ownership is determined by transaction context,
         * never trusted from the incoming payload.
         */
        $data['company_id'] = $companyId;

        return User::create($data);
    }

    public function update(
        int $companyId,
        User $user,
        array $data
    ): bool {
        $ownedUser = User::query()
            ->where('company_id', $companyId)
            ->whereKey($user->getKey())
            ->first();

        if (! $ownedUser) {
            return false;
        }

        /*
         * User company ownership is immutable.
         */
        unset($data['company_id']);

        return $ownedUser->update($data);
    }

    public function delete(
        int $companyId,
        User $user
    ): bool {
        $ownedUser = User::query()
            ->where('company_id', $companyId)
            ->whereKey($user->getKey())
            ->first();

        if (! $ownedUser) {
            return false;
        }

        return (bool) $ownedUser->delete();
    }
}
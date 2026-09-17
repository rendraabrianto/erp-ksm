<?php

namespace App\Services;

use App\Models\User;
use RuntimeException;

class CompanyGuardService
{
    public function assertActorBelongsToCompany(
        int $userId,
        int $companyId
    ): User {
        $user =
            User::query()
                ->whereKey(
                    $userId
                )
                ->firstOrFail();

        if (
            (int) $user->company_id
            !==
            $companyId
        ) {
            throw new RuntimeException(
                'Actor does not belong to transaction company.'
            );
        }

        return $user;
    }
}
<?php

namespace App\Repositories\Eloquent;

use App\Models\Branch;
use App\Repositories\Contracts\BranchRepositoryInterface;

class BranchRepository implements BranchRepositoryInterface
{
    public function paginate(
        int $companyId,
        int $perPage = 10
    ) {
        return Branch::query()
            ->where('company_id', $companyId)
            ->with('company')
            ->latest()
            ->paginate($perPage);
    }

    public function find(
        int $companyId,
        int $id
    ): ?Branch {
        return Branch::query()
            ->where('company_id', $companyId)
            ->whereKey($id)
            ->first();
    }

    public function create(
        int $companyId,
        array $data
    ): Branch
    {
        $data['company_id'] = $companyId;
        return Branch::create($data);
    }

    public function update(
        int $companyId,
        Branch $branch,
        array $data
    ): bool {
        $ownedBranch = Branch::query()
            ->where('company_id', $companyId)
            ->whereKey($branch->getKey())
            ->first();

        if (! $ownedBranch) {
            return false;
        }

        /*
         * Company ownership is immutable through repository update.
         */
        unset($data['company_id']);

        return $ownedBranch->update($data);
    }
}
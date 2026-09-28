<?php

namespace App\Services;

use App\Models\Branch;
use App\Repositories\Contracts\BranchRepositoryInterface;

class BranchService
{
    public function __construct(
        private BranchRepositoryInterface $repository
    ) {}

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
    ): ?Branch {
        return $this->repository->find(
            $companyId,
            $id
        );
    }

    public function update(
        int $companyId,
        Branch $branch,
        array $data
    ): bool {
        return $this->repository->update(
            $companyId,
            $branch,
            $data
        );
    }
}
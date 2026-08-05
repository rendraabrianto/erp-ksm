<?php

namespace App\Services;

use App\Repositories\Contracts\BranchRepositoryInterface;

class BranchService
{
    public function __construct(
        private BranchRepositoryInterface $repository
    ) {}

    public function paginate()
    {
        return $this->repository->paginate();
    }
}
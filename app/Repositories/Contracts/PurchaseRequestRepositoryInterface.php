<?php

namespace App\Repositories\Contracts;

use App\Models\PurchaseRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PurchaseRequestRepositoryInterface
{
    public function paginate(
        int $companyId,
        int $perPage = 10
    ): LengthAwarePaginator;

    public function create(
        array $data
    ): PurchaseRequest;

    public function find(
        int $companyId,
        int $id
    ): ?PurchaseRequest;
}
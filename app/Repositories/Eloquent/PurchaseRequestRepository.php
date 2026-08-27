<?php

namespace App\Repositories\Eloquent;

use App\Models\PurchaseRequest;
use App\Repositories\Contracts\PurchaseRequestRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PurchaseRequestRepository
implements PurchaseRequestRepositoryInterface
{
    public function paginate(
        int $perPage = 10
    ): LengthAwarePaginator
    {
        return PurchaseRequest::with([
            'warehouse'
        ])
        ->latest()
        ->paginate($perPage);
    }

    public function create(
        array $data
    ): PurchaseRequest
    {
        return PurchaseRequest::create($data);
    }

    public function find(
        int $id
    ): ?PurchaseRequest
    {
        return PurchaseRequest::with([
            'details.item',
            'warehouse',
        ])->find($id);
    }
}
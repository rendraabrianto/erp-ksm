<?php

namespace App\Repositories\Eloquent;

use App\Models\PurchaseRequest;
use App\Repositories\Contracts\PurchaseRequestRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PurchaseRequestRepository
implements PurchaseRequestRepositoryInterface
{
    public function paginate(
        int $companyId,
        int $perPage = 10
    ): LengthAwarePaginator
    {
        return PurchaseRequest::query()
            ->where('company_id', $companyId)
            ->with([
                'warehouse',
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
        int $companyId,
        int $id
    ): ?PurchaseRequest
    {
        return PurchaseRequest::query()
            ->where('company_id', $companyId)
            ->with([
                'details.item',
                'warehouse',
            ])
            ->whereKey($id)
            ->first();
    }
}
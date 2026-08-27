<?php

namespace App\Repositories\Eloquent;

use App\Models\GoodsReceipt;

use App\Repositories\Contracts\GoodsReceiptRepositoryInterface;

class GoodsReceiptRepository
implements GoodsReceiptRepositoryInterface
{
    public function create(
        array $data
    ): GoodsReceipt
    {
        return GoodsReceipt::create(
            $data
        );
    }

    public function find(
        int $id
    ): ?GoodsReceipt
    {
        return GoodsReceipt::with(
            'details.item'
        )->find($id);
    }
}
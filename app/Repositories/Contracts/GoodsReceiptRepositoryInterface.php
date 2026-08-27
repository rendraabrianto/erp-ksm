<?php

namespace App\Repositories\Contracts;

use App\Models\GoodsReceipt;

interface GoodsReceiptRepositoryInterface
{
    public function create(
        array $data
    ): GoodsReceipt;

    public function find(
        int $id
    ): ?GoodsReceipt;
}
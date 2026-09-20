<?php

namespace App\Repositories\Contracts;

use App\Models\Item;

interface ItemRepositoryInterface
{
    public function paginate(
        int $companyId,
        int $perPage = 10
    );

    public function create(
        array $data
    ): Item;

    public function find(
        int $id,
        int $companyId
    ): ?Item;
}
<?php

namespace App\Repositories\Eloquent;

use App\Models\Item;
use App\Repositories\Contracts\ItemRepositoryInterface;

class ItemRepository
implements ItemRepositoryInterface
{
    public function paginate(
        int $perPage = 10
    )
    {
        return Item::with([
            'category',
            'uom'
        ])
        ->latest()
        ->paginate($perPage);
    }

    public function create(
        array $data
    ): Item
    {
        return Item::create($data);
    }

    public function find(
        int $id
    ): ?Item
    {
        return Item::find($id);
    }
}
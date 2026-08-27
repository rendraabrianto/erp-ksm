<?php

namespace App\Services;

use App\DTO\ItemDTO;
use App\Models\Item;
use App\Repositories\Contracts\ItemRepositoryInterface;

class ItemService
{
    public function __construct(
        private ItemRepositoryInterface $repository
    ) {}

    public function paginate()
    {
        return $this->repository->paginate();
    }

    public function create(
        ItemDTO $dto
    ): Item
    {
        return $this->repository->create([
            'item_category_id' => $dto->itemCategoryId,
            'uom_id' => $dto->uomId,
            'code' => $dto->code,
            'name' => $dto->name,
            'description' => $dto->description,
            'minimum_stock' => $dto->minimumStock,
            'maximum_stock' => $dto->maximumStock,
            'is_active' => $dto->isActive,
        ]);
    }
}
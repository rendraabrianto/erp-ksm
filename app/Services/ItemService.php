<?php

namespace App\Services;

use App\DTO\ItemDTO;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Repositories\Contracts\ItemRepositoryInterface;
use RuntimeException;

class ItemService
{
    public function __construct(
        private ItemRepositoryInterface $repository
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

    public function create(
        ItemDTO $dto
    ): Item {
        /*
        |--------------------------------------------------------------------------
        | Resolve Item Category
        |--------------------------------------------------------------------------
        */

        $category =
            ItemCategory::query()
                ->whereKey(
                    $dto->itemCategoryId
                )
                ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Company Ownership Guard
        |--------------------------------------------------------------------------
        */

        if (
            (int) $category->company_id
            !==
            $dto->companyId
        ) {
            throw new RuntimeException(
                "Item category does not belong to company {$dto->companyId}."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Persist Item
        |--------------------------------------------------------------------------
        */

        return $this->repository->create([
            'company_id' =>
                $dto->companyId,

            'item_category_id' =>
                $dto->itemCategoryId,

            'uom_id' =>
                $dto->uomId,

            'code' =>
                $dto->code,

            'name' =>
                $dto->name,

            'description' =>
                $dto->description,

            'minimum_stock' =>
                $dto->minimumStock,

            'maximum_stock' =>
                $dto->maximumStock,

            'is_active' =>
                $dto->isActive,
        ]);
    }

    public function find(
        int $id,
        int $companyId
    ): ?Item {
        return $this->repository->find(
            $id,
            $companyId
        );
    }
}
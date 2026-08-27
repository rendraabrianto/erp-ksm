<?php

namespace App\Http\Controllers;

use App\Models\Uom;
use App\Models\ItemCategory;
use App\Services\ItemService;
use App\DTO\ItemDTO;
use App\Http\Requests\StoreItemRequest;

class ItemController extends Controller
{
    public function __construct(
        private ItemService $service
    ) {}

    public function index()
    {
        $items = $this->service->paginate();

        return view(
            'erp.items.index',
            compact('items')
        );
    }

    public function create()
    {
        return view(
            'erp.items.create',
            [
                'categories' => ItemCategory::all(),
                'uoms'       => Uom::all(),
            ]
        );
    }

    public function store(
        StoreItemRequest $request
    ) {

        $dto = new ItemDTO(
            itemCategoryId : $request->item_category_id,
            uomId          : $request->uom_id,
            code           : $request->code,
            name           : $request->name,
            description    : $request->description,
            minimumStock   : $request->minimum_stock ?? 0,
            maximumStock   : $request->maximum_stock ?? 0,
            isActive       : true,
        );

        $this->service->create($dto);

        return redirect()
            ->route('items.index')
            ->with(
                'success',
                'Item created successfully'
            );
    }
}
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
        $companyId =
            (int) auth()->user()->company_id;

        $items =
            $this->service->paginate(
                $companyId
            );

        return view(
            'erp.items.index',
            compact('items')
        );
    }

    public function create()
    {
        $companyId =
            (int) auth()->user()->company_id;

        return view(
            'erp.items.create',
            [
                'categories' =>
                    ItemCategory::query()
                        ->where(
                            'company_id',
                            $companyId
                        )
                        ->orderBy('name')
                        ->get(),

                'uoms' =>
                    Uom::query()
                        ->orderBy('name')
                        ->get(),
            ]
        );
    }

    public function store(
        StoreItemRequest $request
    ) {

        $dto = new ItemDTO(
            companyId       : (int) $request->user()->company_id,
            itemCategoryId  : (int) $request->item_category_id,
            uomId           : (int) $request->uom_id,
            code            : $request->code,
            name            : $request->name,
            description     : $request->description,
            minimumStock    : (float) ($request->minimum_stock ?? 0),
            maximumStock    : (float) ($request->maximum_stock ?? 0),
            isActive        : true,
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
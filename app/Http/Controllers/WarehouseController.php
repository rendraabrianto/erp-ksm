<?php

namespace App\Http\Controllers;

use App\Services\WarehouseService;

class WarehouseController extends Controller
{
    public function __construct(
        private WarehouseService $service
    ) {}

    public function index()
    {
        $companyId =
            (int) auth()->user()->company_id;

        $warehouses =
            $this->service->paginate(
                $companyId
            );

        return view(
            'erp.warehouses.index',
            compact('warehouses')
        );
    }
}
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
        $warehouses = $this->service->paginate();

        return view(
            'erp.warehouses.index',
            compact('warehouses')
        );
    }
}
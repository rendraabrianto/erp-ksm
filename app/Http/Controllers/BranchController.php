<?php

namespace App\Http\Controllers;

use App\Services\BranchService;


class BranchController extends Controller
{
    public function __construct(
        private BranchService $service
    ) {}

    public function index()
    {
        $branches = $this->service->paginate();

        return view(
            'erp.branches.index',
            compact('branches')
        );
    }
}
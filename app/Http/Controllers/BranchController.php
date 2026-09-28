<?php

namespace App\Http\Controllers;

use App\Services\BranchService;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function __construct(
        private BranchService $service
    ) {}

    public function index(Request $request)
    {
        $companyId = (int) $request->user()->company_id;

        $branches = $this->service->paginate(
            $companyId
        );

        return view(
            'erp.branches.index',
            compact('branches')
        );
    }
}
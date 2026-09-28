<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $service
    ) {}

    public function index(Request $request)
    {
        $companyId = (int) $request->user()->company_id;

        $dashboard = $this->service->getDashboardData(
            $companyId
        );

        return view(
            'erp.dashboard.index',
            compact('dashboard')
        );
    }
}
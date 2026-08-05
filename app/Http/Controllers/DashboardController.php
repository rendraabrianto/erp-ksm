<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $service
    ) {}

    public function index()
    {
        $dashboard = $this->service->getDashboardData();
        return view('erp.dashboard.index', compact('dashboard'));
        
    }
}
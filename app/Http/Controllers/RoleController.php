<?php

namespace App\Http\Controllers;

use App\Services\RoleService;

class RoleController extends Controller
{
    public function __construct(
        private RoleService $service
    ) {}

    public function index()
    {
        $roles = $this->service->paginate();

        return view(
            'erp.roles.index',
            compact('roles')
        );
    }
}
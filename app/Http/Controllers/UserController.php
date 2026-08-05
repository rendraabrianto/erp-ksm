<?php

namespace App\Http\Controllers;

use App\Services\UserService;
use App\Models\Company;
use App\Models\Branch;
use Spatie\Permission\Models\Role;
use App\Http\Requests\StoreUserRequest;

class UserController extends Controller
{
    public function __construct(
        private UserService $service
    ) {}
    
    public function create()
    {
        return view(
            'erp.users.create',
            [
                'companies' => Company::all(),
                'branches' => Branch::all(),
                'roles' => Role::all(),
            ]
        );
    }

    public function store(StoreUserRequest $request)
    {
        $this->service->create(
            $request->validated()
        );

        return redirect()
            ->route('users.index')
            ->with(
                'success',
                'User created successfully'
            );
    }

    public function index()
    {
        $users = $this->service->paginate();

        return view(
            'erp.users.index',
            compact('users')
        );
    }
}
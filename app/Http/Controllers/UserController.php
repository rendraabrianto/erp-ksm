<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Models\Branch;
use App\Services\UserService;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        private UserService $service
    ) {}

    public function index(Request $request)
    {
        $companyId = (int) $request->user()->company_id;

        $users = $this->service->paginate(
            $companyId
        );

        return view(
            'erp.users.index',
            compact('users')
        );
    }

    public function create(Request $request)
    {
        $companyId = (int) $request->user()->company_id;

        $branches = Branch::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();

        /*
         * Roles are global authorization configuration
         * in the current ERP architecture.
         */
        $roles = Role::query()
            ->orderBy('name')
            ->get();

        return view(
            'erp.users.create',
            compact(
                'branches',
                'roles'
            )
        );
    }

    public function store(StoreUserRequest $request)
    {
        $companyId = (int) $request->user()->company_id;

        $this->service->create(
            $companyId,
            $request->validated()
        );

        return redirect()
            ->route('users.index')
            ->with(
                'success',
                'User created successfully'
            );
    }
}
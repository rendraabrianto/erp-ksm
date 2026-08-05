<?php

namespace App\Http\Controllers;

use App\DTO\CompanyData;
use App\Models\Company;
use App\Services\CompanyService;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;

class CompanyController extends Controller
{
    public function __construct(
        private CompanyService $service
    ) {}

    public function index()
    {
        $companies = $this->service->getPaginated();

        return view(
            'erp.company.index',
            compact('companies')
        );
    }

    public function create()
    {
        return view('erp.company.create');
    }

    public function store(StoreCompanyRequest $request)
    {
        $dto = new CompanyData(
            code: $request->code,
            name: $request->name,
            phone: $request->phone,
            email: $request->email,
            address: $request->address,
            is_active: $request->boolean('is_active', true),
        );

        $this->service->create($dto);

        return redirect()
            ->route('companies.index')
            ->with('success', 'Company created successfully');
    }

    public function show(Company $company)
    {
        return redirect()->route('companies.index');
    }

    public function edit(Company $company)
    {
        return view(
            'erp.company.edit',
            compact('company')
        );
    }

    // public function update(Company $company)
    // {
    //     abort(501);
    // }
    public function update(
        UpdateCompanyRequest $request,
        Company $company
    )
    {
        $dto = new CompanyData(
            code: $request->code,
            name: $request->name,
            phone: $request->phone,
            email: $request->email,
            address: $request->address,
            is_active: $request->boolean('is_active', true),
        );

        $this->service->update(
            $company,
            $dto
        );

        return redirect()
            ->route('companies.index')
            ->with(
                'success',
                'Company updated successfully'
            );
    }

    public function destroy(Company $company)
    {
        
            $company->delete();

        return redirect()->route('companies.index')
            ->with('success','perusahaan berhasil dihapus');
    }
}
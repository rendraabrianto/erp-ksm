<?php

namespace App\Repositories\Contracts;

use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CompanyRepositoryInterface
{
    public function paginate(int $perPage = 10): LengthAwarePaginator;

    public function findById(int $id): ?Company;

    public function create(array $data): Company;

    public function update(Company $company, array $data): bool;

    public function delete(Company $company): bool;
}
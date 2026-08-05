<?php

namespace App\Services;

use App\DTO\CompanyData;
use App\Models\Company;
use App\Repositories\Contracts\CompanyRepositoryInterface;


class CompanyService
{
    public function __construct(
        private CompanyRepositoryInterface $repository
    ) {}

    public function getPaginated()
    {
        return $this->repository->paginate();
    }

    public function create(CompanyData $dto): Company
    {
        return $this->repository->create([
            'code'      => $dto->code,
            'name'      => $dto->name,
            'phone'     => $dto->phone,
            'email'     => $dto->email,
            'address'   => $dto->address,
            'is_active' => $dto->is_active,
        ]);
    }

    public function update(
        Company $company,
        CompanyData $dto
    ): bool
    {
        return $this->repository->update($company, [
            'code'      => $dto->code,
            'name'      => $dto->name,
            'phone'     => $dto->phone,
            'email'     => $dto->email,
            'address'   => $dto->address,
            'is_active' => $dto->is_active,
        ]);
    }
    public function delete(
        Company $company
    ): bool
    {
        return $this->repository->delete(
            $company
        );
    }
}
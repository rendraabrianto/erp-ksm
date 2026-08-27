<?php

namespace App\Services;

use App\DTO\CustomerDTO;

use App\Repositories\Contracts\CustomerRepositoryInterface;

class CustomerService
{
    public function __construct(
        private CustomerRepositoryInterface $repository,
        private AuditLogService $auditService,
    ) {}

    public function create(
        CustomerDTO $dto
    )
    {
        $customer =
            $this->repository->create([

                'code' =>
                    $dto->code,

                'name' =>
                    $dto->name,

                'phone' =>
                    $dto->phone,

                'email' =>
                    $dto->email,

                'address' =>
                    $dto->address,

                'credit_limit' =>
                    $dto->creditLimit,

                'credit_days' =>
                    $dto->creditDays,
            ]);

        $this->auditService->log(
            module : 'Customer',
            action : 'CREATE',
            referenceType : 'Customer',
            referenceId : $customer->id,
            oldValues : null,
            newValues : [
                'code' => $customer->code,
                'name' => $customer->name,
            ]
        );

        return $customer;
    }
}
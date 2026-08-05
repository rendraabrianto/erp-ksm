<?php

namespace App\Services;

use App\Repositories\Contracts\AuditLogRepositoryInterface;

class AuditLogService
{
    public function __construct(
        private AuditLogRepositoryInterface $repository
    ) {}

    public function log(
        string $module,
        string $action,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    )
    {
        return $this->repository->create([
            'user_id' => auth()->id(),
            'module' => $module,
            'action' => $action,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
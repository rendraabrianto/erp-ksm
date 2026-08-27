<?php

namespace App\Services;

use App\Models\PurchaseRequest;
use Illuminate\Support\Facades\DB;

class PurchaseRequestApprovalService
{
    public function __construct(
        private AuditLogService $auditLogService,
    ) {}

    public function approve(
        PurchaseRequest $pr,
        int $userId
    ): PurchaseRequest
    {
        return DB::transaction(function () use (
            $pr,
            $userId
        ) {

            $oldStatus = $pr->status;

            $pr->update([
                'status' => 'APPROVED',
            ]);

            $this->auditLogService->log(
                module: 'Purchase Request',
                action: 'APPROVE',
                referenceType: 'PurchaseRequest',
                referenceId: $pr->id,
                oldValues: [
                    'status' => $oldStatus,
                ],
                newValues: [
                    'status' => 'APPROVED',
                    'approved_by' => $userId,
                ]
            );

            return $pr->fresh();
        });
    }
}
<?php

namespace App\Services;

use App\DTO\GoodsReceiptDTO;
use App\DTO\InventoryTransactionDTO;

use App\Models\PurchaseOrderDetail;

use App\Repositories\Contracts\GoodsReceiptRepositoryInterface;

use Illuminate\Support\Facades\DB;

class GoodsReceiptService
{
    public function __construct(
        private GoodsReceiptRepositoryInterface $repository,
        private DocumentSequenceService $documentSequenceService,
        private InventoryTransactionService $inventoryService,
        private AuditLogService $auditService,
        private AutoJournalService $autoJournalService,
        private InventoryValuationService $valuationService,
    ) {}

    public function create(
        GoodsReceiptDTO $dto
    )
    {
        return DB::transaction(
            function () use ($dto) {

                $gr = $this->repository->create([
                    'gr_no' => $this
                        ->documentSequenceService
                        ->next('GR'),

                    'purchase_order_id'
                        => $dto->purchaseOrderId,

                    'receipt_date'
                        => now()->toDateString(),

                    'status'
                        => 'POSTED',

                    'remarks'
                        => $dto->remarks,

                    'created_by'
                        => $dto->createdBy,
                ]);

                foreach ($dto->lines as $line) 
                {
                    $poDetail =
                        PurchaseOrderDetail::where(
                            'purchase_order_id',
                            $dto->purchaseOrderId
                        )
                        ->where(
                            'item_id',
                            $line->itemId
                        )
                        ->firstOrFail();

                    $gr->details()->create([
                        'purchase_order_detail_id'=> $poDetail->id,
                        'item_id'=> $line->itemId,
                        'qty_received'=> $line->qty,
                        'unit_cost'=> $line->unitPrice,
                        'remarks'=> $line->remarks,
                    ]);

                    $poDetail->increment(
                        'received_qty',
                        $line->qty
                    );

                    $this->inventoryService->post(
                        new InventoryTransactionDTO(
                            warehouseId : $dto->warehouseId,
                            itemId      : $line->itemId,
                            referenceType : 'GOODS_RECEIPT',
                            referenceId   : $gr->id,
                            qtyIn         : $line->qty,
                            qtyOut        : 0,
                            unitCost      : $line->unitPrice,
                            remarks       : 'Goods Receipt'
                        )
                    );

                    $this->valuationService->recalculate(
                        itemId      : $line->itemId,
                        qtyReceived : $line->qty,
                        unitCost    : $line->unitPrice
                    );
                    
                    $amount =
                        $line->qty
                        *
                        $line->unitPrice;

                    $this->autoJournalService
                        ->goodsReceipt(
                            inventoryAccount : '1201',
                            amount           : $amount,
                            referenceId      : $gr->id,
                            userId           : $dto->createdBy
                        );
                }

                $this->auditService->log(
                    module : 'Goods Receipt',
                    action : 'CREATE',
                    referenceType : 'GoodsReceipt',
                    referenceId : $gr->id,
                    oldValues : null,
                    newValues : [
                        'gr_no' => $gr->gr_no,
                    ]
                );

                return $gr->load(
                    'details'
                );
            }
        );
    }
}
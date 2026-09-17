<?php

namespace Tests\Feature;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Company;
use App\Models\CustomerReceipt;
use App\Models\DeliveryOrder;
use App\Models\GoodsReceipt;
use App\Models\InventoryAdjustment;
use App\Models\InventoryTransfer;
use App\Models\Journal;
use App\Models\PaymentVoucher;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

class TransactionCompanyOwnershipTest extends TestCase
{
    /**
     * @return array<class-string>
     */
    private function transactionModels(): array
    {
        return [
            PurchaseRequest::class,
            PurchaseOrder::class,
            GoodsReceipt::class,
            PurchaseInvoice::class,
            PaymentVoucher::class,

            SalesOrder::class,
            DeliveryOrder::class,
            SalesInvoice::class,
            CustomerReceipt::class,

            AccountPayable::class,
            AccountReceivable::class,

            InventoryAdjustment::class,
            InventoryTransfer::class,

            Journal::class,
        ];
    }

    public function test_transaction_headers_allow_company_id_mass_assignment(): void
    {
        foreach ($this->transactionModels() as $modelClass) {
            $model = new $modelClass();

            $this->assertContains(
                'company_id',
                $model->getFillable(),
                "{$modelClass} must have company_id in fillable."
            );
        }
    }

    public function test_transaction_headers_belong_to_company(): void
    {
        foreach ($this->transactionModels() as $modelClass) {
            $model = new $modelClass();

            $this->assertTrue(
                method_exists($model, 'company'),
                "{$modelClass} must define company()."
            );

            $relation = $model->company();

            $this->assertInstanceOf(
                BelongsTo::class,
                $relation,
                "{$modelClass}::company() must return BelongsTo."
            );

            $this->assertInstanceOf(
                Company::class,
                $relation->getRelated(),
                "{$modelClass}::company() must relate to Company."
            );

            $this->assertSame(
                'company_id',
                $relation->getForeignKeyName(),
                "{$modelClass}::company() must use company_id."
            );
        }
    }
}
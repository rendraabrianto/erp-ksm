<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountingAccountMapping;
use App\Services\AccountingAccountResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class AccountingAccountResolverServiceTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    private AccountingAccountResolverService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->data =
            InventoryReconciliationTestData::create();

        $this->service =
            app(
                AccountingAccountResolverService::class
            );
    }

    public function test_it_resolves_control_accounts_for_company(): void
    {
        $this->assertSame(
            $this->data['grni_account_id'],
            $this->service
                ->grni(
                    $this->data['company_id']
                )
                ->id
        );

        $this->assertSame(
            $this->data['ap_account_id'],
            $this->service
                ->accountsPayable(
                    $this->data['company_id']
                )
                ->id
        );

        $this->assertSame(
            $this->data['ar_account_id'],
            $this->service
                ->accountsReceivable(
                    $this->data['company_id']
                )
                ->id
        );
    }

    public function test_it_rejects_inactive_control_account(): void
    {
        Account::query()
            ->whereKey(
                $this->data['grni_account_id']
            )
            ->update([
                'is_active' => false,
            ]);

        $this->expectException(
            \RuntimeException::class
        );

        $this->expectExceptionMessage(
            'GRNI account is inactive.'
        );

        $this->service->grni(
            $this->data['company_id']
        );
    }

    public function test_it_rejects_header_control_account(): void
    {
        Account::query()
            ->whereKey(
                $this->data['grni_account_id']
            )
            ->update([
                'is_header' => true,
            ]);

        $this->expectException(
            \RuntimeException::class
        );

        $this->expectExceptionMessage(
            'GRNI account cannot be a header account.'
        );

        $this->service->grni(
            $this->data['company_id']
        );
    }

    public function test_company_has_only_one_accounting_mapping(): void
    {
        $this->assertSame(
            1,
            AccountingAccountMapping::query()
                ->where(
                    'company_id',
                    $this->data['company_id']
                )
                ->count()
        );
    }

    public function test_it_rejects_missing_company_accounting_mapping(): void
    {
        AccountingAccountMapping::query()
            ->where(
                'company_id',
                $this->data['company_id']
            )
            ->delete();

        $this->expectException(
            \RuntimeException::class
        );

        $this->expectExceptionMessage(
            sprintf(
                'Accounting account mapping is not configured for company %d.',
                $this->data['company_id']
            )
        );

        $this->service->grni(
            $this->data['company_id']
        );
    }
}
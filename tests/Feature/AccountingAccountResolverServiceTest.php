<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountingAccountMapping;
use App\Services\AccountingAccountResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;


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
    
    public function test_it_rejects_control_account_from_another_company(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Company B
        |--------------------------------------------------------------------------
        */

        $companyBId =
            DB::table('companies')
                ->insertGetId([
                    'code' => 'TEST-B',
                    'name' => 'Test Company B',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | Liability Group B
        |--------------------------------------------------------------------------
        */

        $liabilityGroupBId =
            DB::table('account_groups')
                ->insertGetId([
                    'company_id' => $companyBId,
                    'code' => 'LIA-T',
                    'name' => 'Liability Test Company B',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | GRNI Account B
        |--------------------------------------------------------------------------
        |
        | Same accounting code is intentional.
        |
        | H2.1 changed account uniqueness from global code to:
        |
        |     company_id + code
        |
        | so Company A and Company B may legitimately use the same code.
        |
        */

        $grniAccountBId =
            DB::table('accounts')
                ->insertGetId([
                    'company_id' => $companyBId,
                    'account_group_id' =>
                        $liabilityGroupBId,
                    'code' => '2101',
                    'name' => 'GRNI Company B',
                    'normal_balance' => 'CREDIT',
                    'is_header' => false,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | Corrupt Company A Mapping
        |--------------------------------------------------------------------------
        |
        | Simulate an invalid cross-company mapping:
        |
        | Company A mapping -> Company B GRNI account.
        |
        */

        AccountingAccountMapping::query()
            ->where(
                'company_id',
                $this->data['company_id']
            )
            ->update([
                'grni_account_id' =>
                    $grniAccountBId,
            ]);

        /*
        |--------------------------------------------------------------------------
        | Resolver Must Reject It
        |--------------------------------------------------------------------------
        */

        $this->expectException(
            \RuntimeException::class
        );

        $this->expectExceptionMessage(
            sprintf(
                'GRNI account does not belong to company %d.',
                $this->data['company_id']
            )
        );

        $this->service->grni(
            $this->data['company_id']
        );
    }
}
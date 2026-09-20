<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountingAccountMapping;

class AccountingAccountResolverService
{
    public function getMapping(
        int $companyId
    ): AccountingAccountMapping {
        $mapping =
            AccountingAccountMapping::query()
                ->with([
                    'grniAccount',
                    'apAccount',
                    'arAccount',
                ])
                ->where(
                    'company_id',
                    $companyId
                )
                ->first();

        if (! $mapping) {
            throw new \RuntimeException(
                sprintf(
                    'Accounting account mapping is not configured for company %d.',
                    $companyId
                )
            );
        }

        return $mapping;
    }

    public function grni(
        int $companyId
    ): Account {
        $mapping =
            $this->getMapping(
                $companyId
            );

        return $this->validateAccount(
            $mapping->grniAccount,
            'GRNI',
            $companyId
        );
    }

    public function accountsPayable(
        int $companyId
    ): Account {
        $mapping =
            $this->getMapping(
                $companyId
            );

        return $this->validateAccount(
            $mapping->apAccount,
            'Accounts Payable',
            $companyId
        );
    }

    public function accountsReceivable(
        int $companyId
    ): Account {
        $mapping =
            $this->getMapping(
                $companyId
            );

        return $this->validateAccount(
            $mapping->arAccount,
            'Accounts Receivable',
            $companyId
        );
    }

    private function validateAccount(
        ?Account $account,
        string $label,
        int $companyId
    ): Account {
        if (! $account) {
            throw new \RuntimeException(
                "{$label} account mapping is not configured."
            );
        }

        if (
            (int) $account->company_id
            !==
            $companyId
        ) {
            throw new \RuntimeException(
                "{$label} account does not belong to company {$companyId}."
            );
        }

        if ($account->is_header) {
            throw new \RuntimeException(
                "{$label} account cannot be a header account."
            );
        }

        if (! $account->is_active) {
            throw new \RuntimeException(
                "{$label} account is inactive."
            );
        }

        return $account;
    }

    public function accountForCompany(
        int $accountId,
        int $companyId,
        string $label = 'Account'
    ): \App\Models\Account {

        $account =
            \App\Models\Account::query()
                ->whereKey($accountId)
                ->firstOrFail();

        if (
            (int) $account->company_id
            !==
            $companyId
        ) {
            throw new \RuntimeException(
                "{$label} does not belong to company {$companyId}."
            );
        }

        if ($account->is_header) {
            throw new \RuntimeException(
                "{$label} cannot be a header account."
            );
        }

        if (! $account->is_active) {
            throw new \RuntimeException(
                "{$label} is inactive."
            );
        }

        return $account;
    }

}
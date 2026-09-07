<?php

namespace App\Services;

use App\Models\AccountingAccountMapping;
use App\Models\Account;

class AccountingAccountResolverService
{
    public function getMapping(int $companyId): AccountingAccountMapping
    {
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

        if (!$mapping) {
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
            'GRNI'
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
            'Accounts Payable'
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
            'Accounts Receivable'
        );
    }

    private function validateAccount(
        ?Account $account,
        string $label
    ): Account {
        if (!$account) {
            throw new \RuntimeException(
                "{$label} account mapping is not configured."
            );
        }

        if ($account->is_header) {
            throw new \RuntimeException(
                "{$label} account cannot be a header account."
            );
        }

        if (!$account->is_active) {
            throw new \RuntimeException(
                "{$label} account is inactive."
            );
        }

        return $account;
    }
}
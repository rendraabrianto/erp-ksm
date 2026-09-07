<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingAccountMapping extends Model
{
    protected $fillable = [
        'company_id',
        'grni_account_id',
        'ap_account_id',
        'ar_account_id',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(
            Company::class
        );
    }

    public function grniAccount(): BelongsTo
    {
        return $this->belongsTo(
            Account::class,
            'grni_account_id'
        );
    }

    public function apAccount(): BelongsTo
    {
        return $this->belongsTo(
            Account::class,
            'ap_account_id'
        );
    }

    public function arAccount(): BelongsTo
    {
        return $this->belongsTo(
            Account::class,
            'ar_account_id'
        );
    }
}
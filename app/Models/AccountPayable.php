<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountPayable extends Model
{
    protected $fillable = [
        'reference_type',
        'reference_id',
        'supplier_name',
        'invoice_date',
        'due_date',
        'amount',
        'paid_amount',
        'balance_amount',
        'status',
        'company_id',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(
            Company::class
        );
    }
}

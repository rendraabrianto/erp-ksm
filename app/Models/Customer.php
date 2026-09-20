<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'phone',
        'email',
        'address',
        'credit_limit',
        'credit_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' =>
                'decimal:2',

            'credit_days' =>
                'integer',

            'is_active' =>
                'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(
            Company::class
        );
    }

    public function receivables(): HasMany
    {
        return $this->hasMany(
            AccountReceivable::class
        );
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'account_group_id',
        'code',
        'name',
        'normal_balance',
        'is_header',
        'is_active',
    ];

    protected $casts = [
        'is_header' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(
            AccountGroup::class,
            'account_group_id'
        );
    }

    public function journalDetails(): HasMany
    {
        return $this->hasMany(
            JournalDetail::class
        );
    }
}
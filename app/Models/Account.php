<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use SoftDeletes;

    protected $fillable = [
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
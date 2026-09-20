<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'is_active',

        'inventory_account_id',
        'cogs_account_id',
        'sales_account_id',
        'adjustment_gain_account_id',
        'adjustment_loss_account_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(
            Company::class
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            Item::class
        );
    }

    public function inventoryAccount(): BelongsTo
    {
        return $this->belongsTo(
            Account::class,
            'inventory_account_id'
        );
    }

    public function cogsAccount(): BelongsTo
    {
        return $this->belongsTo(
            Account::class,
            'cogs_account_id'
        );
    }

    public function salesAccount(): BelongsTo
    {
        return $this->belongsTo(
            Account::class,
            'sales_account_id'
        );
    }

    public function adjustmentGainAccount(): BelongsTo
    {
        return $this->belongsTo(
            Account::class,
            'adjustment_gain_account_id'
        );
    }

    public function adjustmentLossAccount(): BelongsTo
    {
        return $this->belongsTo(
            Account::class,
            'adjustment_loss_account_id'
        );
    }
}
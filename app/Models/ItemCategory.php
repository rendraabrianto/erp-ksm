<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',

        'inventory_account_id',
        'cogs_account_id',
        'sales_account_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function inventoryAccount()
    {
        return $this->belongsTo(
            Account::class,
            'inventory_account_id'
        );
    }

    public function cogsAccount()
    {
        return $this->belongsTo(
            Account::class,
            'cogs_account_id'
        );
    }

    public function salesAccount()
    {
        return $this->belongsTo(
            Account::class,
            'sales_account_id'
        );
    }
}
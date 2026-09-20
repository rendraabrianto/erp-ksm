<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    protected $fillable = [
        'code',
        'name',
        'phone',
        'email',
        'address',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function accountGroups(): HasMany
    {
        return $this->hasMany(AccountGroup::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function accountingAccountMapping(): HasOne
    {
        return $this->hasOne(
            AccountingAccountMapping::class
        );
    }
    
    public function itemCategories(): HasMany
    {
        return $this->hasMany(
            ItemCategory::class
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            Item::class
        );
    }

    public function customers(): HasMany
    {
        return $this->hasMany(
            Customer::class
        );
    }
}
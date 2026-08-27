<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'phone',
        'email',
        'address',
        'credit_limit',
        'credit_days',
        'is_active',
    ];

    public function receivables()
    {
        return $this->hasMany(
            AccountReceivable::class
        );
    }
}
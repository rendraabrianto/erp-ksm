<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\SalesOrderDetail;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'so_no',
        'customer_id',
        'order_date',
        'delivery_date',
        'status',
        'remarks',
        'created_by',
        'company_id',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(
            Company::class
        );
    }

    public function customer()
    {
        return $this->belongsTo(
            Customer::class
        );
    }

    public function details()
    {
        return $this->hasMany(
            SalesOrderDetail::class
        );
    }
}
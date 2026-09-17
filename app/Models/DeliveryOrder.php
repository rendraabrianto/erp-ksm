<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'do_no',
        'sales_order_id',
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

    public function salesOrder()
    {
        return $this->belongsTo(
            SalesOrder::class
        );
    }

    public function details()
    {
        return $this->hasMany(
            DeliveryOrderDetail::class
        );
    }
}
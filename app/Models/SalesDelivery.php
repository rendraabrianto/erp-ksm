<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesDelivery extends Model
{
    use SoftDeletes;

    protected $fillable = [

        'do_no',

        'sales_order_id',

        'delivery_date',

        'status',

        'remarks',

        'created_by',
    ];

    public function details()
    {
        return $this->hasMany(
            SalesDeliveryDetail::class
        );
    }

    public function salesOrder()
    {
        return $this->belongsTo(
            SalesOrder::class
        );
    }
}

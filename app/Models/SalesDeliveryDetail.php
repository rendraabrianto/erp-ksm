<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesDeliveryDetail extends Model
{
    protected $fillable = [

        'sales_delivery_id',

        'sales_order_detail_id',

        'item_id',

        'qty_delivered',

        'unit_cost',

        'remarks',
    ];

    public function item()
    {
        return $this->belongsTo(
            Item::class
        );
    }

    public function salesOrderDetail()
    {
        return $this->belongsTo(
            SalesOrderDetail::class
        );
    }
}

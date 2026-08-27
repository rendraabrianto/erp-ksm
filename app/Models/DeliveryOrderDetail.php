<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryOrderDetail extends Model
{
    protected $fillable = [
        'delivery_order_id',
        'sales_order_detail_id',
        'item_id',
        'qty',
        'unit_cost',
        'remarks',
    ];

    public function deliveryOrder()
    {
        return $this->belongsTo(
            DeliveryOrder::class
        );
    }

    public function item()
    {
        return $this->belongsTo(
            Item::class
        );
    }
}
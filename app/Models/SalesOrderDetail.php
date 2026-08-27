<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrderDetail extends Model
{
    protected $fillable = [
        'sales_order_id',
        'item_id',
        'qty',
        'unit_price',
        'discount',
        'delivered_qty',
        'remarks',
    ];

    public function salesOrder()
    {
        return $this->belongsTo(
            SalesOrder::class
        );
    }

    public function item()
    {
        return $this->belongsTo(
            Item::class
        );
    }
}
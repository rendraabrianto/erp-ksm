<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\SalesOrderDetail;

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
    ];

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
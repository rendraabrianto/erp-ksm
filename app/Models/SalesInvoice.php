<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesInvoice extends Model
{
    use SoftDeletes;

    protected $fillable = [

        'invoice_no',
        'customer_id',
        'delivery_order_id',
        'invoice_date',
        'due_date',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'grand_total',
        'status',
        'remarks',
        'created_by',
    ];

    protected $casts = [

        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(
            Customer::class
        );
    }

    public function deliveryOrder()
    {
        return $this->belongsTo(
            DeliveryOrder::class
        );
    }

    public function details()
    {
        return $this->hasMany(
            SalesInvoiceDetail::class
        );
    }
    
    public function receivable()
    {
        return $this->hasOne(
            AccountReceivable::class
        );
    }
}
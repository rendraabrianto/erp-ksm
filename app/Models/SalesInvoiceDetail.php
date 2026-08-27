<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesInvoiceDetail extends Model
{
    protected $fillable = [

        'sales_invoice_id',
        'item_id',
        'qty',
        'unit_price',
        'discount',
        'line_total',
        'remarks',
    ];

    protected $casts = [

        'qty' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function salesInvoice()
    {
        return $this->belongsTo(
            SalesInvoice::class
        );
    }

    public function item()
    {
        return $this->belongsTo(
            Item::class
        );
    }
}
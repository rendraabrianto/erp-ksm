<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseInvoiceDetail extends Model
{
    protected $fillable = [
        'purchase_invoice_id',
        'item_id',
        'qty',
        'unit_price',
        'amount',
        'remarks',
    ];
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseInvoice extends Model
{
    protected $fillable = [
        'invoice_no',
        'invoice_date',
        'goods_receipt_id',
        'supplier_name',
        'supplier_invoice_no',
        'subtotal',
        'tax_amount',
        'grand_total',
        'status',
        'created_by',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(
            PurchaseInvoiceDetail::class
        );
    }
}
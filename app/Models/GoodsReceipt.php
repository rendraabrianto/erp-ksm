<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceipt extends Model
{
    protected $fillable = [
        'gr_no',
        'purchase_order_id',
        'receipt_date',
        'status',
        'remarks',
        'created_by',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(
            GoodsReceiptDetail::class
        );
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(
            PurchaseOrder::class
        );
    }
}
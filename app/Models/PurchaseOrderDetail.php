<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderDetail extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'item_id',
        'qty',
        'received_qty',
        'unit_price',
        'remarks',
    ];

    // public function purchaseOrder(): BelongsTo
    // {
    //     return $this->belongsTo(
    //         PurchaseOrder::class
    //     );
    // }

    // public function item(): BelongsTo
    // {
    //     return $this->belongsTo(
    //         Item::class
    //     );
    // }
    public function purchaseOrder()
    {
        return $this->belongsTo(
            PurchaseOrder::class
        );
    }

    public function item()
    {
        return $this->belongsTo(
            Item::class
        );
    }
}
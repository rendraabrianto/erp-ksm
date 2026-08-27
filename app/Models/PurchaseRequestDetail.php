<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseRequestDetail extends Model
{
    protected $fillable = [
        'purchase_request_id',
        'item_id',
        'qty',
        'remarks',
    ];

    public function item()
    {
        return $this->belongsTo(
            Item::class
        );
    }

    public function purchaseRequest()
    {
        return $this->belongsTo(
            PurchaseRequest::class
        );
    }
}

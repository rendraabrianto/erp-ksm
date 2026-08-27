<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'po_no',
        'purchase_request_id',
        'po_date',
        'supplier_name',
        'remarks',
        'status',
        'created_by',
    ];

    // public function details(): HasMany
    // {
    //     return $this->hasMany(
    //         PurchaseOrderDetail::class
    //     );
    // }

    public function details()
    {
        return $this->hasMany(
            PurchaseOrderDetail::class
        );
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(
            PurchaseRequest::class
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }
}
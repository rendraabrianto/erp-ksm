<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequest extends Model
{
    protected $fillable = [
        'pr_no',
        'pr_date',
        'warehouse_id',
        'remarks',
        'status',
        'created_by',
    ];

    public function details()
    {
        return $this->hasMany(
            PurchaseRequestDetail::class
        );
    }

    public function warehouse()
    {
        return $this->belongsTo(
            Warehouse::class
        );
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(
            PurchaseOrder::class
        );
    }
}

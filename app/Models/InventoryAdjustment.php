<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryAdjustment extends Model
{
    protected $fillable = [
        'adjustment_no',
        'adjustment_date',
        'warehouse_id',
        'status',
        'reason',
        'remarks',
        'created_by',
        'posted_by',
        'posted_at',
    ];

    protected $casts = [
        'adjustment_date' => 'date',
        'posted_at' => 'datetime',
    ];

    public function warehouse()
    {
        return $this->belongsTo(
            \App\Models\Warehouse::class
        );
    }

    public function creator()
    {
        return $this->belongsTo(
            \App\Models\User::class,
            'created_by'
        );
    }

    public function poster()
    {
        return $this->belongsTo(
            \App\Models\User::class,
            'posted_by'
        );
    }

    public function details()
    {
        return $this->hasMany(
            \App\Models\InventoryAdjustmentDetail::class
        );
    }
}
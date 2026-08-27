<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAdjustmentDetail extends Model
{
    protected $fillable = [
        'inventory_adjustment_id',
        'item_id',
        'system_qty',
        'physical_qty',
        'adjustment_qty',
        'unit_cost',
        'total_cost',
        'remarks',
    ];

    protected $casts = [
        'system_qty' => 'float',
        'physical_qty' => 'float',
        'adjustment_qty' => 'float',
        'unit_cost' => 'float',
        'total_cost' => 'float',
    ];

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(
            InventoryAdjustment::class,
            'inventory_adjustment_id'
        );
    }

    public function item()
    {
        return $this->belongsTo(
            \App\Models\Item::class
        );
    }
}
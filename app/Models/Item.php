<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Item extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'item_category_id',
        'uom_id',
        'code',
        'name',
        'description',
        'minimum_stock',
        'maximum_stock',
        'average_cost',
        'last_purchase_price',
        'is_active',
    ];

    protected $casts = [
        'minimum_stock' => 'decimal:4',
        'maximum_stock' => 'decimal:4',
        'average_cost' => 'decimal:2',
        'last_purchase_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(
            ItemCategory::class,
            'item_category_id'
        );
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(
            Uom::class
        );
    }
}
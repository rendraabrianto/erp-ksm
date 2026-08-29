<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransferDetail extends Model
{
    protected $fillable = [
        'inventory_transfer_id',
        'item_id',
        'qty',
        'unit_cost',
        'total_cost',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(
            InventoryTransfer::class,
            'inventory_transfer_id'
        );
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(
            Item::class
        );
    }
}
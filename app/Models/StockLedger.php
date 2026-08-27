<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLedger extends Model
{
    protected $fillable = [
        'warehouse_id',
        'item_id',
        'transaction_date',
        'reference_type',
        'reference_id',
        'qty_in',
        'qty_out',
        'balance_qty',
        'unit_cost',
        'total_cost',
        'remarks',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'qty_in' => 'decimal:4',
        'qty_out' => 'decimal:4',
        'balance_qty' => 'decimal:4',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(
            Warehouse::class
        );
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(
            Item::class
        );
    }
}
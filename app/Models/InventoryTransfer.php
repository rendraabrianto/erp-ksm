<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryTransfer extends Model
{
    protected $fillable = [
        'transfer_no',
        'transfer_date',
        'source_warehouse_id',
        'destination_warehouse_id',
        'status',
        'remarks',
        'created_by',
        'posted_by',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'transfer_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(
            Warehouse::class,
            'source_warehouse_id'
        );
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(
            Warehouse::class,
            'destination_warehouse_id'
        );
    }

    public function details(): HasMany
    {
        return $this->hasMany(
            InventoryTransferDetail::class
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'posted_by'
        );
    }

    public function isDraft(): bool
    {
        return $this->status === 'DRAFT';
    }

    public function isPosted(): bool
    {
        return $this->status === 'POSTED';
    }
}
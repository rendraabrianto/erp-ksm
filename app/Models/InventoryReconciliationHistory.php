<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryReconciliationHistory extends Model
{
    protected $fillable = [
        'warehouse_id',
        'item_id',
        'date_from',
        'date_to',
        'missing_before',
        'mismatch_before',
        'orphan_before',
        'recovery_posted',
        'correction_posted',
        'reversal_posted',
        'journal_posted_count',
        'missing_after',
        'mismatch_after',
        'orphan_after',
        'is_reconciled_after',
        'executed_by',
        'executed_at',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'is_reconciled_after' => 'boolean',
        'executed_at' => 'datetime',
    ];

    public function warehouse()
    {
        return $this->belongsTo(
            Warehouse::class
        );
    }

    public function item()
    {
        return $this->belongsTo(
            Item::class
        );
    }

    public function executor()
    {
        return $this->belongsTo(
            User::class,
            'executed_by'
        );
    }
}
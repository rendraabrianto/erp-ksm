<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Journal extends Model
{
    protected $fillable = [
        'journal_date',
        'journal_no',
        'reference_type',
        'reference_id',
        'description',
        // 'status',
        'created_by',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(
            JournalDetail::class
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
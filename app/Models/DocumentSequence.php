<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    protected $fillable = [
        'document_type',
        'prefix',
        'description',
        'current_number',
        'padding',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
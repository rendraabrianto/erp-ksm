<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountPayable extends Model
{
    protected $fillable = [

        'reference_type',
        'reference_id',

        'supplier_name',

        'invoice_date',
        'due_date',

        'amount',
        'paid_amount',
        'balance_amount',

        'status',
    ];
}
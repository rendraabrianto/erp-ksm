<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountReceivable extends Model
{
    use SoftDeletes;

    protected $fillable = [

        'customer_id',
        'sales_invoice_id',
        'invoice_date',
        'due_date',
        'amount',
        'paid_amount',
        'balance_amount',
        'status',
        'remarks',
    ];

    protected $casts = [

        'invoice_date' => 'date',
        'due_date' => 'date',
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(
            Customer::class
        );
    }

    public function salesInvoice()
    {
        return $this->belongsTo(
            SalesInvoice::class
        );
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerReceipt extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'receipt_no',
        'customer_id',
        'account_receivable_id',
        'cash_bank_account_id',
        'receipt_date',
        'amount',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(
            Customer::class
        );
    }

    public function accountReceivable()
    {
        return $this->belongsTo(
            AccountReceivable::class
        );
    }

    public function cashBankAccount()
    {
        return $this->belongsTo(
            Account::class,
            'cash_bank_account_id'
        );
    }
}
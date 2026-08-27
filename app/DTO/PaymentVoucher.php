<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentVoucher extends Model
{
    use SoftDeletes;

    protected $fillable = [

        'voucher_no',
        'voucher_date',

        'account_payable_id',

        'cash_bank_account_id',

        'amount',

        'payment_method',

        'remarks',

        'created_by',
    ];

    public function accountPayable()
    {
        return $this->belongsTo(
            AccountPayable::class
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
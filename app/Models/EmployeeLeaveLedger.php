<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeLeaveLedger extends Model
{
    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'leave_balance_id',
        'transaction_type',
        'amount',
        'total_earned',
        'credit_addition',
        'credit_deduction',
        'balance'
    ];
}

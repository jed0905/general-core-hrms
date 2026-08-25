<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollEmployeeDeduction extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_number',
        'deduction_id',
        'amount',
        'date_start',
        'date_end',
        'deduction_period',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_number', 'id');
    }

    public function deduction()
    {
        return $this->belongsTo(PayrollDeduction::class, 'deduction_id', 'id');
    }
}

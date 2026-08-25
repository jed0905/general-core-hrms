<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollEmployeeProjectFund extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_number',
        'project_fund_id',
        'status',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'id', 'employee_number');
    }

    public function projectFund()
    {
        return $this->belongsTo(PayrollProjectFund::class, 'project_fund_id', 'id');
    }
}

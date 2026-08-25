<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollEmployeeAccountInformation extends Model
{
    use HasFactory;

    protected $table = 'payroll_employee_account_information';

    protected $fillable = [
        'employee_number',
        'account_type_id',
        'account_number',
        'status',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_number', 'id');
    }

    public function accountType()
    {
        return $this->belongsTo(PayrollAccountType::class, 'account_type_id', 'id');
    }
}

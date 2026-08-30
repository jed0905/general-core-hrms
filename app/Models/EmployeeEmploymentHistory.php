<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeEmploymentHistory extends Model
{
    protected $fillable = [
        'employee_id',
        'job_title_id',
        'department_id',
        'salary',
        'pay_grade',
        'effective_from',
        'effective_to',
        'movement_id'
    ];
}

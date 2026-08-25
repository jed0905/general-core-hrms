<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkExperience extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'from',
        'to',
        'position_title',
        'department_agency',
        'monthly_salary',
        'salary_grade',
        'status_of_appointment',
        'government_service',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}

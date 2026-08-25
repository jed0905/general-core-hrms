<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollCalendar extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_number',
        'date_start',
        'date_end',
        'employee_status',
        'employee_type',
        'payroll_type_id',
        'operating_unit_id'
    ];

    public function operatingUnit(){
        return $this->belongsTo(OperatingUnit::class, 'operating_unit_id', 'id');
    }

    public function employeeStatus(){
        return $this->belongsTo(JobStatus::class, 'employee_status', 'id');
    }

}

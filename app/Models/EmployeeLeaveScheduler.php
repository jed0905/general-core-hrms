<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeLeaveScheduler extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'leave_id',
        'run_day',
        'credits_to_add',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function personalInformation()
    {
        return $this->belongsTo(PersonalInformation::class, 'employee_id', 'employee_id');
    }

    public function leave()
    {
        return $this->belongsTo(Leave::class);
    }
}

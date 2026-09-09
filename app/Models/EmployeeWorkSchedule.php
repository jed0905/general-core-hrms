<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeWorkSchedule extends Model
{
    protected $fillable = [
        'employee_id',
        'work_schedule_id',
        'effective_from',
        'effective_to',
        'is_primary',
        'remarks'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }

    public function workSchedule()
    {
        return $this->belongsTo(WorkSchedule::class, 'work_schedule_id', 'id');
    }
}

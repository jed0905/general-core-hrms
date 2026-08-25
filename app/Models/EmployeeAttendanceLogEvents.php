<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeAttendanceLogEvents extends Model
{
    use HasFactory;
    protected $fillable = [
        'employee_id',
        'date',
        'att_log_event_type_id',
        'coverage',
        'start_time',
        'end_time',
        'remarks',
        'status',
        'approved_by',
        'source',
    ];

    public function attendanceLogEventType()
    {
        return $this->belongsTo(AttendanceLogEventTypes::class, 'att_log_event_type_id', 'id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function documents()
    {
        return $this->hasMany(EmployeeAttendanceLogDocuments::class, 'log_event_id', 'id');
    }
}

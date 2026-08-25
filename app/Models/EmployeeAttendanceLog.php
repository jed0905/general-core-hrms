<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeAttendanceLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'auth_date_time',
        'auth_date',
        'auth_time',
        'direction',
        'device_name',
        'device_sn',
        'person_name',
        'card_no',
        'notified_at',
        'broadcasted_at',
    ];

    public function employee(){
        return $this->belongsTo(Employee::class, 'employee_id', 'biometrics_id');
    }

    public $timestamps = false;
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeBiometricId extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'biometric_id',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }

    public function logs()
    {
        return $this->hasMany(EmployeeAttendanceLog::class, 'employee_id', 'biometric_id');
    }

}

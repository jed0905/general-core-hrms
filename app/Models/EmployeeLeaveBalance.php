<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeLeaveBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'balance',
        'used',
        'pending',
        'as_of_date',
    ];

    /**
     * Days that can still be filed: reserved (pending) days are not available.
     */
    public function available(): float
    {
        return round((float) $this->balance - (float) $this->pending, 2);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }
}

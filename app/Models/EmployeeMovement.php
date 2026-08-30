<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'movement_type_id',

        'effective_date',
        'reference_number',
        'reason',
        'remarks',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'implemented_at'
    ];



    // Relationships
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function previousPosition()
    {
        return $this->belongsTo(Position::class, 'previous_position_id');
    }

    public function newPosition()
    {
        return $this->belongsTo(Position::class, 'new_position_id');
    }

    public function previousDepartment()
    {
        return $this->belongsTo(Department::class, 'previous_department_id');
    }

    public function newDepartment()
    {
        return $this->belongsTo(Department::class, 'new_department_id');
    }

    public function previousDesignation()
    {
        return $this->belongsTo(Designation::class, 'previous_designation_id');
    }

    public function newDesignation()
    {
        return $this->belongsTo(Designation::class, 'new_designation_id');
    }

    public function previousOperatingUnit()
    {
        return $this->belongsTo(OperatingUnit::class, 'previous_operating_unit_id');
    }

    public function newOperatingUnit()
    {
        return $this->belongsTo(OperatingUnit::class, 'new_operating_unit_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}

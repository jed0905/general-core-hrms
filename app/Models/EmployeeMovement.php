<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'old_employee_number',
        'new_employee_number',
        'movement_type',
        'previous_position_id',
        'previous_department_id',
        'previous_designation_id',
        'previous_operating_unit_id',
        'previous_salary',
        'previous_salary_grade',
        'previous_salary_step',
        'new_position_id',
        'new_department_id',
        'new_designation_id',
        'new_operating_unit_id',
        'new_salary',
        'new_salary_grade',
        'new_salary_step',
        'effective_date',
        'document_file_path',
        'reason',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'previous_salary' => 'decimal:2',
        'new_salary' => 'decimal:2',
    ];

    // Helper method to get movement types for select dropdown
    public static function getMovementTypes()
    {
        return [
            'promotion' => 'Promotion',
            'transfer' => 'Transfer',
            'reassignment' => 'Reassignment',
            'demotion' => 'Demotion',
            'reinstatement' => 'Reinstatement',
            'reemployment' => 'Reemployment',
            'separation' => 'Separation',
            'retirement' => 'Retirement',
            'resignation' => 'Resignation',
            'termination' => 'Termination',
            'suspension' => 'Suspension',
            'salary_adjustment' => 'Salary Adjustment',
            'position_change' => 'Position Change',
            'department_change' => 'Department Change',
            'designation_change' => 'Designation Change',
        ];
    }

    // Helper method to get status options for select dropdown
    public static function getStatusOptions()
    {
        return [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'implemented' => 'Implemented',
            'cancelled' => 'Cancelled',
        ];
    }

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

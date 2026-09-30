<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveApprovalWorkflowStep extends Model
{
    use HasFactory;

    /**
     * Types that resolve to exactly one employee from the current org model.
     */
    public const SUPPORTED_TYPES = [
        'immediate_supervisor',
        'higher_supervisor',
        'specific_employee',
    ];

    /**
     * Present in the column enum but not resolvable yet:
     * - role: many users can hold a role, and nothing says which one approves.
     * - designation: there is no designation entity in this HRMS.
     */
    public const UNSUPPORTED_TYPES = [
        'role',
        'designation',
    ];

    protected $fillable = [
        'leave_approval_workflow_id',
        'step_order',
        'approver_type',
        'approver_employee_id',
        'approver_role',
        'is_required',
    ];

    protected $casts = [
        'leave_approval_workflow_id' => 'integer',
        'step_order' => 'integer',
        'approver_employee_id' => 'integer',
        'is_required' => 'boolean',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(LeaveApprovalWorkflow::class, 'leave_approval_workflow_id');
    }

    /**
     * Employee approver, when approver_type is specific_employee.
     */
    public function approverEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_employee_id');
    }

    /**
     * Approval instances that were resolved from this step.
     */
    public function applicationApprovals(): HasMany
    {
        return $this->hasMany(LeaveApplicationApproval::class, 'leave_approval_workflow_step_id');
    }
}

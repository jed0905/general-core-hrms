<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A concrete approval instance for a submitted application.
 *
 * Rows are resolved from the workflow at submission time and keep a
 * snapshot of the step (type, approver name). They must never be
 * recomputed from the current workflow configuration.
 */
class LeaveApplicationApproval extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'leave_application_id',
        'leave_approval_workflow_id',
        'leave_approval_workflow_step_id',
        'approver_id',
        'approver_type',
        'approver_name',
        'approval_order',
        'is_required',
        'status',
        'acted_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'leave_application_id' => 'integer',
            'leave_approval_workflow_id' => 'integer',
            'leave_approval_workflow_step_id' => 'integer',
            'approver_id' => 'integer',
            'approval_order' => 'integer',
            'is_required' => 'boolean',
            'acted_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LeaveApplication::class, 'leave_application_id', 'id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_id');
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(LeaveApprovalWorkflow::class, 'leave_approval_workflow_id');
    }

    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(LeaveApprovalWorkflowStep::class, 'leave_approval_workflow_step_id');
    }
}

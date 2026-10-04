<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An approval instance created when a requisition is submitted. It is a
 * snapshot (approver, order, type, workflow name): editing the workflow later
 * never changes it.
 */
class JobRequisitionApproval extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'job_requisition_id',
        'approval_workflow_id',
        'approval_workflow_step_id',
        'workflow_name',
        'approval_order',
        'approver_type',
        'is_required',
        'approver_id',
        'approver_name',
        'status',
        'acted_at',
        'acted_by',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'job_requisition_id' => 'integer',
            'approval_workflow_id' => 'integer',
            'approval_workflow_step_id' => 'integer',
            'approval_order' => 'integer',
            'is_required' => 'boolean',
            'approver_id' => 'integer',
            'acted_by' => 'integer',
            'acted_at' => 'datetime',
        ];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(JobRequisition::class, 'job_requisition_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_id');
    }
}

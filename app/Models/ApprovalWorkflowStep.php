<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalWorkflowStep extends Model
{
    public const TYPE_IMMEDIATE_SUPERVISOR = 'immediate_supervisor';

    public const TYPE_HIGHER_SUPERVISOR = 'higher_supervisor';

    public const TYPE_SPECIFIC_EMPLOYEE = 'specific_employee';

    public const TYPES = [self::TYPE_IMMEDIATE_SUPERVISOR, self::TYPE_HIGHER_SUPERVISOR, self::TYPE_SPECIFIC_EMPLOYEE];

    protected $fillable = [
        'approval_workflow_id',
        'step_order',
        'approver_type',
        'approver_employee_id',
        'is_required',
    ];

    protected function casts(): array
    {
        return [
            'approval_workflow_id' => 'integer',
            'step_order' => 'integer',
            'approver_employee_id' => 'integer',
            'is_required' => 'boolean',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }

    public function approverEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_employee_id');
    }
}

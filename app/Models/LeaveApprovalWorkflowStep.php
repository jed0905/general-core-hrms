<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveApprovalWorkflowStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'approval_order',
        'approver_type',
        'approver_id',
        'is_required',
        'is_active',
    ];

    protected $casts = [
        'approval_order' => 'integer',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Applications that used this approval step.
     */
    public function applicationApprovals(): HasMany
    {
        return $this->hasMany(
            LeaveApplicationApproval::class,
            'leave_approval_step_id'
        );
    }

    /**
     * Employee approver, when approver_type is employee.
     */
    public function approver()
    {
        return $this->belongsTo(
            Employee::class,
            'approver_id'
        );
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveApprovalWorkflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'leave_policy_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * The leave policy this workflow belongs to.
     */
    public function leavePolicy()
    {
        return $this->belongsTo(LeavePolicy::class);
    }

    /**
     * The approval steps in this workflow.
     */
    public function steps()
    {
        return $this->hasMany(LeaveApprovalWorkflowStep::class)
            ->orderBy('approval_order');
    }
}

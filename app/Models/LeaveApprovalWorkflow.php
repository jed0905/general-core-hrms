<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'leave_policy_id' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * The leave policy this workflow belongs to (null = company-wide default).
     */
    public function leavePolicy(): BelongsTo
    {
        return $this->belongsTo(LeavePolicy::class);
    }

    /**
     * The approval steps in this workflow.
     */
    public function steps(): HasMany
    {
        return $this->hasMany(LeaveApprovalWorkflowStep::class)
            ->orderBy('step_order');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An offer approval instance, created when the offer is submitted. A snapshot of
 * the "job_offer" approval workflow (approver, order, type, workflow name):
 * editing the workflow later never changes it.
 */
class JobOfferApproval extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'job_offer_id',
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
            'job_offer_id' => 'integer',
            'approval_workflow_id' => 'integer',
            'approval_workflow_step_id' => 'integer',
            'approval_order' => 'integer',
            'is_required' => 'boolean',
            'approver_id' => 'integer',
            'acted_by' => 'integer',
            'acted_at' => 'datetime',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(JobOffer::class, 'job_offer_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}

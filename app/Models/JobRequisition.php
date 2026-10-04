<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An internal request to hire. Status changes only through RequisitionService /
 * RequisitionApprovalService; never assign status directly.
 */
class JobRequisition extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_CANCELLED];

    /** States a requisition may be cancelled from (approved only while no active vacancy uses it; see service). */
    public const CANCELLABLE = [self::STATUS_DRAFT, self::STATUS_PENDING, self::STATUS_APPROVED];

    public const REASON_NEW_POSITION = 'new_position';

    public const REASON_REPLACEMENT = 'replacement';

    public const REASONS = [self::REASON_NEW_POSITION, self::REASON_REPLACEMENT];

    protected $fillable = [
        'requisition_number',
        'department_id',
        'job_title_id',
        'location_id',
        'employment_status_id',
        'positions',
        'reason',
        'replaced_employee_id',
        'justification',
        'target_start_date',
        'requested_by_employee_id',
        'created_by',
        'status',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'job_title_id' => 'integer',
            'location_id' => 'integer',
            'employment_status_id' => 'integer',
            'positions' => 'integer',
            'replaced_employee_id' => 'integer',
            'requested_by_employee_id' => 'integer',
            'created_by' => 'integer',
            'cancelled_by' => 'integer',
            'target_start_date' => 'date:Y-m-d',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function employmentStatus(): BelongsTo
    {
        return $this->belongsTo(EmploymentStatus::class);
    }

    public function replacedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'replaced_employee_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requested_by_employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(JobRequisitionApproval::class)->orderBy('approval_order');
    }

    public function vacancies(): HasMany
    {
        return $this->hasMany(Vacancy::class);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * The approval step that may act now: the lowest-order pending step.
     */
    public function currentApproval(): ?JobRequisitionApproval
    {
        return $this->approvals()->where('status', JobRequisitionApproval::STATUS_PENDING)->orderBy('approval_order')->first();
    }

    /**
     * Openings already allocated to vacancies that are not cancelled.
     */
    public function allocatedOpenings(): int
    {
        return (int) $this->vacancies()->where('status', '!=', Vacancy::STATUS_CANCELLED)->sum('openings');
    }
}

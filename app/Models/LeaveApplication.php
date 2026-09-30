<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveApplication extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Statuses in which nobody can act on the application any more.
     */
    public const TERMINAL_STATUSES = [self::STATUS_REJECTED, self::STATUS_CANCELLED];

    /**
     * Statuses that close the conversation (no more comments or attachments).
     */
    public const FINAL_STATUSES = [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_CANCELLED];

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'reason',
        'total_days',
        'total_hours',
        'status',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'cancelled_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'leave_type_id' => 'integer',
            'total_days' => 'decimal:2',
            'total_hours' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function dates()
    {
        return $this->hasMany(LeaveApplicationDate::class);
    }

    public function attachments()
    {
        return $this->hasMany(LeaveApplicationAttachment::class);
    }

    public function approvals()
    {
        return $this->hasMany(LeaveApplicationApproval::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(LeaveApplicationComment::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(LeaveApplicationStatusHistory::class);
    }

    /**
     * The approval step that has to be acted on next (lowest pending order).
     */
    public function currentApproval(): ?LeaveApplicationApproval
    {
        return $this->approvals()
            ->where('status', LeaveApplicationApproval::STATUS_PENDING)
            ->orderBy('approval_order')
            ->first();
    }

    /**
     * Append a row to the status history (acted_by is an employee id).
     */
    public function recordStatus(string $status, ?int $actedBy, ?string $remarks = null): LeaveApplicationStatusHistory
    {
        return $this->statusHistories()->create([
            'status' => $status,
            'acted_by' => $actedBy,
            'acted_at' => now(),
            'remarks' => $remarks,
        ]);
    }

    public function isOwnedBy(?int $employeeId): bool
    {
        return $employeeId !== null && (int) $this->employee_id === $employeeId;
    }

    public function hasApprover(?int $employeeId): bool
    {
        return $employeeId !== null
            && $this->approvals()->where('approver_id', $employeeId)->exists();
    }
}

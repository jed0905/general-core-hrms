<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One task of an onboarding: a snapshot of its template task (title, rules,
 * assignee and due date resolved when the onboarding was created), so later
 * template or supervisor changes never rewrite it.
 *
 * pending → in_progress → completed; pending → completed; pending/in_progress →
 * skipped (optional tasks only) or cancelled (when the onboarding is cancelled).
 * Verification is recorded separately and never replaces who completed it.
 */
class OnboardingTask extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED, self::STATUS_SKIPPED, self::STATUS_CANCELLED];

    public const OPEN = [self::STATUS_PENDING, self::STATUS_IN_PROGRESS];

    protected $fillable = [
        'onboarding_id',
        'onboarding_template_task_id',
        'sort_order',
        'title',
        'description',
        'category',
        'assignee_type',
        'assignee_employee_id',
        'is_required',
        'employee_visible',
        'requires_verification',
        'required_document_type_id',
        'due_date',
        'status',
        'started_at',
        'started_by',
        'completed_at',
        'completed_by',
        'completion_remarks',
        'employee_document_id',
        'verified_at',
        'verified_by',
        'skipped_at',
        'skipped_by',
        'skip_reason',
        'cancelled_at',
        'created_by',
    ];

    protected $appends = ['is_overdue'];

    protected function casts(): array
    {
        return [
            'onboarding_id' => 'integer',
            'onboarding_template_task_id' => 'integer',
            'sort_order' => 'integer',
            'assignee_employee_id' => 'integer',
            'is_required' => 'boolean',
            'employee_visible' => 'boolean',
            'requires_verification' => 'boolean',
            'required_document_type_id' => 'integer',
            'due_date' => 'date:Y-m-d',
            'started_at' => 'datetime',
            'started_by' => 'integer',
            'completed_at' => 'datetime',
            'completed_by' => 'integer',
            'employee_document_id' => 'integer',
            'verified_at' => 'datetime',
            'verified_by' => 'integer',
            'skipped_at' => 'datetime',
            'skipped_by' => 'integer',
            'cancelled_at' => 'datetime',
            'created_by' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $task) {
            $from = $task->getOriginal('status');
            if (in_array($from, [self::STATUS_SKIPPED, self::STATUS_CANCELLED], true)) {
                throw new LogicException("A {$from} onboarding task can't change.");
            }
            // A completed task may only gain its verification; who/when completed it never changes.
            if ($from === self::STATUS_COMPLETED && array_diff(array_keys($task->getDirty()), ['verified_at', 'verified_by', 'updated_at'])) {
                throw new LogicException('A completed onboarding task can only be verified.');
            }
        });
        static::deleting(fn () => throw new LogicException('Onboarding tasks are never deleted.'));
    }

    /** Overdue: still open and past its due date (computed, never stored). */
    public function getIsOverdueAttribute(): bool
    {
        return in_array($this->status, self::OPEN, true) && $this->due_date !== null && $this->due_date->lt(today());
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereIn('onboarding_tasks.status', self::OPEN)->whereDate('onboarding_tasks.due_date', '<', today());
    }

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(Onboarding::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assignee_employee_id');
    }

    public function requiredDocumentType(): BelongsTo
    {
        return $this->belongsTo(EmployeeDocumentType::class, 'required_document_type_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(EmployeeDocument::class, 'employee_document_id');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    /** Counts toward completion: completed, and verified when verification is required. */
    public function isSatisfied(): bool
    {
        return $this->status === self::STATUS_COMPLETED && (! $this->requires_verification || $this->verified_at !== null);
    }
}

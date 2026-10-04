<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * An employee's onboarding case. Status changes only through OnboardingService:
 * pending → in_progress (first task activity) → completed, or → cancelled.
 * Completed and cancelled cases are history and never change.
 */
class Onboarding extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED, self::STATUS_CANCELLED];

    /** At most one per employee (database-enforced). */
    public const ACTIVE = [self::STATUS_PENDING, self::STATUS_IN_PROGRESS];

    protected $fillable = [
        'employee_id',
        'onboarding_template_id',
        'template_name',
        'application_conversion_id',
        'status',
        'start_date',
        'target_completion_date',
        'notes',
        'created_by',
        'completed_at',
        'completed_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected $hidden = ['active_employee_id'];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'onboarding_template_id' => 'integer',
            'application_conversion_id' => 'integer',
            'start_date' => 'date:Y-m-d',
            'target_completion_date' => 'date:Y-m-d',
            'created_by' => 'integer',
            'completed_at' => 'datetime',
            'completed_by' => 'integer',
            'cancelled_at' => 'datetime',
            'cancelled_by' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $onboarding) {
            if (! in_array($onboarding->getOriginal('status'), self::ACTIVE, true)) {
                throw new LogicException("A {$onboarding->getOriginal('status')} onboarding is history and can't change.");
            }
        });
        static::deleting(fn () => throw new LogicException('Onboarding records are never deleted; cancel them instead.'));
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(OnboardingTemplate::class, 'onboarding_template_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(OnboardingTask::class)->orderBy('due_date')->orderBy('sort_order')->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(OnboardingEvent::class)->orderBy('id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(OnboardingNote::class)->latest('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }
}

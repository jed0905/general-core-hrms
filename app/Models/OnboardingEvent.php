<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only onboarding audit trail.
 */
class OnboardingEvent extends Model
{
    public const CREATED = 'created';

    public const TASK_CREATED = 'task_created';

    public const TASK_ASSIGNED = 'task_assigned';

    public const TASK_STARTED = 'task_started';

    public const TASK_COMPLETED = 'task_completed';

    public const TASK_VERIFIED = 'task_verified';

    public const TASK_SKIPPED = 'task_skipped';

    public const TASK_CANCELLED = 'task_cancelled';

    public const STARTED = 'started';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'onboarding_id',
        'onboarding_task_id',
        'event',
        'actor_id',
        'remarks',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'onboarding_id' => 'integer',
            'onboarding_task_id' => 'integer',
            'actor_id' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Onboarding history is immutable.'));
        static::deleting(fn () => throw new LogicException('Onboarding history is immutable.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(OnboardingTask::class, 'onboarding_task_id');
    }
}

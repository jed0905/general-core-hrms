<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only log of an interview's time changes (the first row's previous_*
 * values are the original schedule).
 */
class InterviewReschedule extends Model
{
    protected $fillable = [
        'application_interview_id',
        'previous_starts_at',
        'previous_ends_at',
        'new_starts_at',
        'new_ends_at',
        'reason',
        'rescheduled_by',
        'rescheduled_at',
    ];

    protected function casts(): array
    {
        return [
            'application_interview_id' => 'integer',
            'previous_starts_at' => 'datetime',
            'previous_ends_at' => 'datetime',
            'new_starts_at' => 'datetime',
            'new_ends_at' => 'datetime',
            'rescheduled_by' => 'integer',
            'rescheduled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Interview reschedule history is immutable.'));
        static::deleting(fn () => throw new LogicException('Interview reschedule history is immutable.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rescheduled_by');
    }
}

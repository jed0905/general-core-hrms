<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Computed attendance for one employee on one work date (the shift's start
 * date). Recomputed from raw events and approved adjustments until locked.
 */
class AttendanceDay extends Model
{
    public const STATUSES = ['present', 'late', 'incomplete', 'absent', 'rest_day', 'holiday', 'on_leave', 'no_schedule', 'excused'];

    public const MINUTE_COLUMNS = [
        'scheduled_minutes', 'worked_minutes', 'regular_minutes', 'late_minutes', 'undertime_minutes',
        'overtime_minutes', 'approved_overtime_minutes', 'night_minutes',
    ];

    protected $fillable = [
        'employee_id', 'work_date', 'timezone', 'day_type', 'holiday_type', 'holiday_id', 'shift_id', 'scheduled_start',
        'scheduled_end', ...self::MINUTE_COLUMNS, 'first_in', 'last_out', 'leave_days', 'absence_days', 'status',
        'buckets', 'exceptions', 'event_ids', 'pairs', 'attendance_policy_id', 'computed_at', 'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date:Y-m-d',
            'scheduled_start' => UtcDateTime::class,
            'scheduled_end' => UtcDateTime::class,
            'first_in' => UtcDateTime::class,
            'last_out' => UtcDateTime::class,
            'leave_days' => 'decimal:4',
            'absence_days' => 'decimal:4',
            'buckets' => 'array',
            'exceptions' => 'array',
            'event_ids' => 'array',
            'pairs' => 'array',
            'computed_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (AttendanceDay $day) {
            if ($day->getOriginal('locked_at') !== null) {
                throw new LogicException('A locked attendance day cannot be recomputed.');
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function holiday(): BelongsTo
    {
        return $this->belongsTo(Holiday::class);
    }
}

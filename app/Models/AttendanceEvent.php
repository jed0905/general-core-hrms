<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * A raw attendance event from any source. Append-only: the device time string,
 * timezone and normalized instant are never rewritten. Status may change only
 * from "unmatched" to "accepted" when an enrollment is later mapped.
 */
class AttendanceEvent extends Model
{
    public const SOURCES = ['device_pull', 'device_push', 'connector', 'simulator', 'manual', 'import', 'adjustment'];

    public const TYPES = ['check_in', 'check_out', 'break_out', 'break_in', 'overtime_in', 'overtime_out', 'unknown'];

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_UNMATCHED = 'unmatched';

    public const STATUS_INVALID = 'invalid';

    /** Columns that may change after insert (late mapping of an unmatched event). */
    private const MUTABLE = ['employee_id', 'biometric_enrollment_id', 'status', 'status_reason', 'updated_at'];

    protected $fillable = [
        'source', 'attendance_device_id', 'biometric_enrollment_id', 'employee_id', 'attendance_device_sync_run_id',
        'vendor', 'device_model', 'device_user_id', 'device_event_id', 'event_time_raw', 'device_timezone', 'occurred_at',
        'local_date', 'event_type', 'verify_mode', 'payload', 'received_at', 'dedupe_key', 'status', 'status_reason',
        'remarks', 'created_by',
    ];

    protected $hidden = ['payload', 'dedupe_key'];

    protected function casts(): array
    {
        return [
            'occurred_at' => UtcDateTime::class,
            'local_date' => 'date:Y-m-d',
            'received_at' => 'datetime',
            'payload' => 'encrypted:array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (AttendanceEvent $event) {
            if (array_diff(array_keys($event->getDirty()), self::MUTABLE) !== []) {
                throw new LogicException('Raw attendance events are append-only; record an attendance adjustment instead.');
            }
            if ($event->getOriginal('status') !== self::STATUS_UNMATCHED) {
                throw new LogicException('Only unmatched attendance events can be mapped after ingestion.');
            }
        });

        static::deleting(fn () => throw new LogicException('Raw attendance events are never deleted.'));
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(BiometricEnrollment::class, 'biometric_enrollment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

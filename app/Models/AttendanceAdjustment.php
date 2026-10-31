<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * A requested correction to one work date. Raw events are never edited:
 * add_punch creates an "adjustment" event on approval, void_punch excludes an
 * event from calculation, approve_overtime and mark_excused change the computed
 * day. Decided adjustments never change.
 */
class AttendanceAdjustment extends Model
{
    public const TYPES = ['add_punch', 'void_punch', 'approve_overtime', 'mark_excused'];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'employee_id', 'work_date', 'type', 'attendance_event_id', 'original', 'corrected', 'reason', 'remarks', 'status',
        'requested_by', 'reviewed_by', 'reviewed_at', 'review_remarks', 'created_event_id',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date:Y-m-d',
            'original' => 'array',
            'corrected' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (AttendanceAdjustment $adjustment) {
            if ($adjustment->getOriginal('status') !== self::STATUS_PENDING) {
                throw new LogicException('Decided attendance adjustments cannot be changed.');
            }
        });
        static::deleting(fn () => throw new LogicException('Attendance adjustments are kept for audit.'));
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(AttendanceEvent::class, 'attendance_event_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

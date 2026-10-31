<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maps an employee to a device-specific user ID. Only identity references are
 * stored (no templates or images); the card number is encrypted. Rows are
 * deactivated, never deleted, so the enrollment history is preserved.
 */
class BiometricEnrollment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'attendance_device_id', 'employee_id', 'device_user_id', 'card_number', 'external_reference', 'status',
        'enrolled_on', 'enrolled_by', 'deactivated_at', 'deactivated_by', 'remarks',
    ];

    protected $hidden = ['card_number', 'active_device_user_key'];

    protected $attributes = ['status' => self::STATUS_ACTIVE];

    protected function casts(): array
    {
        return [
            'card_number' => 'encrypted',
            'enrolled_on' => 'date:Y-m-d',
            'deactivated_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function enrolledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }
}

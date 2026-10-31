<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One synchronization, push batch, import or connection test of a device,
 * with counters and the (credential-free) error.
 */
class AttendanceDeviceSyncRun extends Model
{
    protected $fillable = [
        'attendance_device_id', 'attendance_connector_id', 'trigger', 'status', 'started_at', 'finished_at', 'fetched',
        'inserted', 'duplicates', 'unmatched', 'invalid', 'attempts', 'error_code', 'error_message', 'window', 'triggered_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'window' => 'array',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}

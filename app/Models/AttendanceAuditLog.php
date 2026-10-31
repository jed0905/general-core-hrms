<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only audit of device, credential, enrollment, adjustment and summary
 * actions. Never contains credentials or biometric data.
 */
class AttendanceAuditLog extends Model
{
    protected $fillable = ['auditable_type', 'auditable_id', 'event', 'changes', 'actor_id', 'actor_label', 'occurred_at'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Attendance audit logs are append-only.'));
        static::deleting(fn () => throw new LogicException('Attendance audit logs are append-only.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

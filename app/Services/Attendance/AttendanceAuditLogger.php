<?php

namespace App\Services\Attendance;

use App\Models\AttendanceAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only attendance audit trail. Hidden attributes (credentials, card
 * numbers, payloads) are recorded as "[redacted]", never with their values.
 */
class AttendanceAuditLogger
{
    public function record(Model $model, string $event, ?User $actor, array $changes = [], ?string $actorLabel = null): void
    {
        AttendanceAuditLog::create([
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'event' => $event,
            'changes' => $changes ?: null,
            'actor_id' => $actor?->id,
            'actor_label' => $actorLabel,
            'occurred_at' => now(),
        ]);
    }

    /** Dirty attributes with old/new values, secrets redacted; call before save(). */
    public function pendingChanges(Model $model): array
    {
        $hidden = $model->getHidden();
        $changes = [];

        foreach ($model->exists ? $model->getDirty() : $model->getAttributes() as $key => $value) {
            if (in_array($key, ['created_at', 'updated_at'], true)) {
                continue;
            }
            $changes[$key] = in_array($key, $hidden, true)
                ? '[redacted]'
                : ['from' => $model->exists ? $model->getOriginal($key) : null, 'to' => $value];
        }

        return $changes;
    }
}

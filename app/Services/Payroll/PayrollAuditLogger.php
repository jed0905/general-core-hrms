<?php

namespace App\Services\Payroll;

use App\Models\PayrollAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes the append-only payroll audit log for configuration, compensation and
 * statutory rule changes. Values go to the database only, never to app logs;
 * encrypted/hidden attributes are recorded as changed without their values.
 */
class PayrollAuditLogger
{
    public function record(Model $model, string $event, ?User $actor, ?array $changes = null): void
    {
        PayrollAuditLog::create([
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'event' => $event,
            'changes' => $changes ?? $this->changes($model, $event),
            'actor_id' => $actor?->id,
            'occurred_at' => now(),
        ]);
    }

    /** Dirty attributes with old and new values; call before save(). */
    public function pendingChanges(Model $model): array
    {
        return $this->changes($model, $model->exists ? 'updated' : 'created');
    }

    private function changes(Model $model, string $event): array
    {
        $hidden = $model->getHidden();
        $values = $model->exists && $event !== 'created' ? $model->getDirty() : $model->getAttributes();
        unset($values['updated_at'], $values['created_at']);

        $changes = [];
        foreach ($values as $key => $value) {
            $changes[$key] = in_array($key, $hidden, true)
                ? '[redacted]'
                : ['from' => $event === 'created' ? null : $model->getOriginal($key), 'to' => $value];
        }

        return $changes;
    }
}

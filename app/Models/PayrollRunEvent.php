<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only audit log of a payroll run's lifecycle actions.
 */
class PayrollRunEvent extends Model
{
    protected $fillable = ['payroll_run_id', 'event', 'from_status', 'to_status', 'actor_id', 'remarks', 'occurred_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Payroll run events are append-only.'));
        static::deleting(fn () => throw new LogicException('Payroll run events are append-only.'));
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

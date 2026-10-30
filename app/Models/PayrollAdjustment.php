<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * A one-time earning or deduction (bonus, commission, correction) for a period.
 *
 *   pending -> approved -> applied (when the run that paid it is finalized)
 *
 * Applied adjustments never change.
 */
class PayrollAdjustment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_APPLIED = 'applied';

    protected $fillable = [
        'payroll_period_id', 'employee_id', 'pay_component_id', 'amount', 'currency', 'remarks',
        'corrects_run_employee_id', 'status', 'applied_run_id', 'created_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (PayrollAdjustment $adjustment) {
            if ($adjustment->getOriginal('status') === self::STATUS_APPLIED) {
                throw new LogicException('Applied payroll adjustments cannot be changed.');
            }
        });

        static::deleting(function (PayrollAdjustment $adjustment) {
            if ($adjustment->status === self::STATUS_APPLIED) {
                throw new LogicException('Applied payroll adjustments cannot be deleted.');
            }
        });
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payComponent(): BelongsTo
    {
        return $this->belongsTo(PayComponent::class);
    }

    public function correctsPayslip(): BelongsTo
    {
        return $this->belongsTo(PayrollRunEmployee::class, 'corrects_run_employee_id');
    }

    public function appliedRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'applied_run_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One effective-dated salary record of an employment assignment (the salary
 * history). Records never overlap per assignment. Only approved records are paid.
 * Once a finalized run has used a record, its terms can't change; only
 * effective_to may still be set (to end it).
 */
class EmployeeCompensation extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const BASES = ['annual', 'monthly', 'semi_monthly', 'biweekly', 'weekly', 'daily', 'hourly'];

    /** Bases paid from time worked rather than as a fixed periodic salary. */
    public const TIME_BASED = ['daily', 'hourly'];

    protected $table = 'employee_compensations';

    protected $fillable = [
        'employee_id', 'employment_assignment_id', 'basis', 'amount', 'currency', 'effective_from',
        'effective_to', 'pay_grade_id', 'salary_structure_id', 'reason', 'status', 'created_by',
        'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'effective_from' => 'date:Y-m-d',
            'effective_to' => 'date:Y-m-d',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (EmployeeCompensation $compensation) {
            $changedTerms = array_diff(array_keys($compensation->getDirty()), ['effective_to', 'updated_at']);

            if ($changedTerms !== [] && $compensation->usedByFinalizedRun()) {
                throw new LogicException('Compensation used by a finalized payroll run cannot be changed; record a new compensation instead.');
            }
        });

        static::deleting(function (EmployeeCompensation $compensation) {
            if ($compensation->status === self::STATUS_APPROVED) {
                throw new LogicException('Approved compensation records are kept as salary history.');
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(EmploymentAssignment::class, 'employment_assignment_id');
    }

    public function payGrade(): BelongsTo
    {
        return $this->belongsTo(PayGrade::class);
    }

    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isTimeBased(): bool
    {
        return in_array($this->basis, self::TIME_BASED, true);
    }

    /** True when a finalized run's snapshot references this record. */
    public function usedByFinalizedRun(): bool
    {
        return PayrollRunEmployee::query()
            ->where('employment_assignment_id', $this->getOriginal('employment_assignment_id'))
            ->whereHas('run', fn ($q) => $q->where('status', PayrollRun::STATUS_FINALIZED))
            ->get(['id', 'snapshot'])
            ->contains(fn (PayrollRunEmployee $payslip) => in_array($this->id, $payslip->snapshot['compensation_ids'] ?? [], true));
    }
}

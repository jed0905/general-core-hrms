<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * One employee's result in one run: the payslip. snapshot holds the inputs it was
 * calculated from (compensation, assignment, working-time basis, time and leave
 * quantities, exchange rates, rule version IDs, engine version) so it is
 * reproducible. Immutable once its run is finalized.
 */
class PayrollRunEmployee extends Model
{
    public const STATUS_INCLUDED = 'included';

    public const STATUS_EXCLUDED = 'excluded';

    public const TOTAL_COLUMNS = ['gross_pay', 'employee_deductions', 'tax', 'net_pay', 'employer_contributions', 'employer_cost', 'taxable_income'];

    protected $fillable = [
        'payroll_run_id', 'employee_id', 'employment_assignment_id', 'status', 'exclusion_reason', 'payslip_number',
        'currency', ...self::TOTAL_COLUMNS, 'snapshot', 'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'gross_pay' => 'decimal:4',
            'employee_deductions' => 'decimal:4',
            'tax' => 'decimal:4',
            'net_pay' => 'decimal:4',
            'employer_contributions' => 'decimal:4',
            'employer_cost' => 'decimal:4',
            'taxable_income' => 'decimal:4',
            'snapshot' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        $guard = function (PayrollRunEmployee $payslip) {
            if (PayrollRun::whereKey($payslip->payroll_run_id)->whereIn('status', PayrollRun::TERMINAL)->exists()) {
                throw new LogicException('Payslips of a finalized or cancelled payroll run cannot be changed.');
            }
        };

        static::updating($guard);
        static::deleting($guard);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(EmploymentAssignment::class, 'employment_assignment_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollRunLine::class)->orderBy('sequence');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(PayrollRunException::class);
    }

    public function disbursement(): HasOne
    {
        return $this->hasOne(PayrollDisbursement::class);
    }
}

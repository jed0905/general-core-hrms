<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One payslip line. amount is already rounded to the currency's minor units;
 * inputs and statutory_rule_version_id make it reproducible.
 * Employer contributions never reduce net pay.
 */
class PayrollRunLine extends Model
{
    public const CLASS_EARNING = 'earning';

    public const CLASS_EMPLOYEE_DEDUCTION = 'employee_deduction';

    public const CLASS_EMPLOYEE_TAX = 'employee_tax';

    public const CLASS_EMPLOYER_CONTRIBUTION = 'employer_contribution';

    public const CLASS_INFORMATIONAL = 'informational';

    public const CLASSES = [
        self::CLASS_EARNING, self::CLASS_EMPLOYEE_DEDUCTION, self::CLASS_EMPLOYEE_TAX,
        self::CLASS_EMPLOYER_CONTRIBUTION, self::CLASS_INFORMATIONAL,
    ];

    protected $fillable = [
        'payroll_run_id', 'payroll_run_employee_id', 'sequence', 'line_class', 'code', 'description',
        'pay_component_id', 'quantity', 'rate', 'multiplier', 'amount', 'currency', 'tax_treatment',
        'contribution_treatment', 'statutory_rule_version_id', 'payroll_adjustment_id', 'reverses_line_id', 'inputs',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'quantity' => 'decimal:4',
            'rate' => 'decimal:8',
            'multiplier' => 'decimal:8',
            'amount' => 'decimal:4',
            'inputs' => 'array',
        ];
    }

    protected static function booted(): void
    {
        $guard = function (PayrollRunLine $line) {
            if (PayrollRun::whereKey($line->payroll_run_id)->whereIn('status', PayrollRun::TERMINAL)->exists()) {
                throw new LogicException('Lines of a finalized or cancelled payroll run cannot be changed.');
            }
        };

        static::updating($guard);
        static::deleting($guard);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(PayrollRunEmployee::class, 'payroll_run_employee_id');
    }

    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(StatutoryRuleVersion::class, 'statutory_rule_version_id');
    }

    public function payComponent(): BelongsTo
    {
        return $this->belongsTo(PayComponent::class);
    }
}

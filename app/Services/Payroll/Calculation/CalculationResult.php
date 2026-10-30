<?php

namespace App\Services\Payroll\Calculation;

/**
 * One employee's calculated payslip: rounded lines, totals (decimal strings in
 * the pay currency), the snapshot to persist and any exceptions.
 */
final class CalculationResult
{
    /**
     * @param  array<int, PayLine>  $lines  rounded, in sequence order
     * @param  array<string, string>  $totals  gross_pay, employee_deductions, tax, net_pay, employer_contributions, employer_cost, taxable_income
     * @param  array<string, mixed>  $snapshot
     * @param  array<int, array{severity: string, code: string, message: string}>  $exceptions
     * @param  array<int, string>  $ruleVersions  id => verification status
     */
    public function __construct(
        public readonly array $lines,
        public readonly array $totals,
        public readonly array $snapshot,
        public readonly array $exceptions,
        public readonly array $ruleVersions,
    ) {}
}

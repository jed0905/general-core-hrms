<?php

namespace App\Services\Reports\Payroll;

use App\Services\Reports\Concerns\FiltersEmployees;
use App\Services\Reports\Report;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Taxable income and income tax withheld per employee, from finalized payslips.
 */
class PayrollTaxReport extends Report
{
    use FiltersEmployees;
    use PayrollReportSupport;

    public static function key(): string
    {
        return 'payroll-tax-withheld';
    }

    public function title(): string
    {
        return 'Tax Withheld';
    }

    public function description(): string
    {
        return 'Taxable income and income tax withheld per employee and currency.';
    }

    public function filters(): array
    {
        return $this->periodFilters();
    }

    public function rules(): array
    {
        return $this->periodRules();
    }

    public function notes(): array
    {
        return [...$this->notesCommon(), 'Use the pay-date range of the country\'s tax year. Official tax forms (e.g. annual certificates) are not generated.'];
    }

    public function columns(array $filters = []): array
    {
        return ['employee_number' => 'Employee No.', 'employee' => 'Employee', 'entity' => 'Legal Entity', 'currency' => 'Currency', 'payslips' => 'Payslips', 'gross' => 'Gross', 'taxable' => 'Taxable Income', 'tax' => 'Tax Withheld'];
    }

    protected function tiebreaker(): string|array
    {
        return ['e.id', 'r.legal_entity_id', 're.currency'];
    }

    public function query(array $filters): Builder
    {
        return $this->scopedRuns(DB::table('payroll_run_employees as re')
            ->join('payroll_runs as r', 'r.id', '=', 're.payroll_run_id')
            ->join('payroll_periods as pp', 'pp.id', '=', 'r.payroll_period_id')
            ->join('legal_entities as le', 'le.id', '=', 'r.legal_entity_id')
            ->join('employees as e', 'e.id', '=', 're.employee_id')
            ->where('re.status', 'included'), $filters)
            ->selectRaw('e.id as employee_id, e.employee_number, e.emp_last_name, e.emp_first_name, r.legal_entity_id, le.name as entity, re.currency, count(*) as payslips, sum(re.gross_pay) as gross, sum(re.taxable_income) as taxable, sum(re.tax) as tax')
            ->groupBy('e.id', 'e.employee_number', 'e.emp_last_name', 'e.emp_first_name', 'r.legal_entity_id', 'le.name', 're.currency')
            ->orderBy('e.emp_last_name')
            ->orderBy('e.id')
            ->orderBy('r.legal_entity_id')
            ->orderBy('re.currency');
    }

    public function mapRow(mixed $row, array $filters): array
    {
        return [
            'employee_number' => $row->employee_number, 'employee' => self::personName($row->emp_last_name, $row->emp_first_name),
            'entity' => $row->entity, 'currency' => $row->currency, 'payslips' => (int) $row->payslips,
            'gross' => $this->money($row->gross, $row->currency), 'taxable' => $this->money($row->taxable, $row->currency), 'tax' => $this->money($row->tax, $row->currency),
        ];
    }
}

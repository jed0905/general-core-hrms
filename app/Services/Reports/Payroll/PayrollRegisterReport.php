<?php

namespace App\Services\Reports\Payroll;

use App\Services\Reports\Concerns\FiltersEmployees;
use App\Services\Reports\Report;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Payroll register: one row per finalized payslip.
 */
class PayrollRegisterReport extends Report
{
    use FiltersEmployees;
    use PayrollReportSupport;

    public static function key(): string
    {
        return 'payroll-register';
    }

    public function title(): string
    {
        return 'Payroll Register';
    }

    public function description(): string
    {
        return 'Every finalized payslip: gross, deductions, tax, net pay and employer cost per employee.';
    }

    public function filters(): array
    {
        return [...$this->periodFilters(), ...$this->employeeFilters(['search', 'department_id', 'include_sub_departments'])];
    }

    public function rules(): array
    {
        return [...$this->periodRules(), ...$this->employeeFilterRules()];
    }

    public function notes(): array
    {
        return $this->notesCommon();
    }

    public function columns(array $filters = []): array
    {
        return [
            'payslip' => 'Payslip', 'run_number' => 'Run', 'pay_date' => 'Pay Date', 'employee_number' => 'Employee No.',
            'employee' => 'Employee', 'department' => 'Department', 'currency' => 'Currency', 'gross' => 'Gross',
            'deductions' => 'Employee Deductions', 'tax' => 'Tax', 'net' => 'Net Pay', 'employer_cost' => 'Employer Cost',
        ];
    }

    public function sortable(): array
    {
        return ['employee' => 'e.emp_last_name', 'pay_date' => 'pp.pay_date', 'net' => 're.net_pay'];
    }

    protected function tiebreaker(): string|array
    {
        return 're.id';
    }

    public function query(array $filters): Builder
    {
        return $this->applyEmployeeFilters(
            $this->scopedRuns(DB::table('payroll_run_employees as re')
                ->join('payroll_runs as r', 'r.id', '=', 're.payroll_run_id')
                ->join('payroll_periods as pp', 'pp.id', '=', 'r.payroll_period_id')
                ->join('employees as e', 'e.id', '=', 're.employee_id')
                ->where('re.status', 'included'), $filters),
            $filters
        )->select(['re.*', 'r.run_number', 'pp.pay_date', 'e.employee_number', 'e.emp_last_name', 'e.emp_first_name', 'e.department_id'])
            ->orderBy('pp.pay_date')
            ->orderBy('e.emp_last_name')
            ->orderBy('re.id');
    }

    public function mapRow(mixed $row, array $filters): array
    {
        $m = fn ($v) => $this->money($v, $row->currency);

        return [
            'payslip' => $row->payslip_number, 'run_number' => $row->run_number, 'pay_date' => substr($row->pay_date, 0, 10),
            'employee_number' => $row->employee_number, 'employee' => self::personName($row->emp_last_name, $row->emp_first_name),
            'department' => $this->tree()->path($row->department_id), 'currency' => $row->currency, 'gross' => $m($row->gross_pay),
            'deductions' => $m($row->employee_deductions), 'tax' => $m($row->tax), 'net' => $m($row->net_pay), 'employer_cost' => $m($row->employer_cost),
        ];
    }
}

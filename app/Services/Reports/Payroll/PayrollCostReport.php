<?php

namespace App\Services\Reports\Payroll;

use App\Services\Reports\Concerns\FiltersEmployees;
use App\Services\Reports\Report;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Employer payroll cost by legal entity, department and currency.
 */
class PayrollCostReport extends Report
{
    use FiltersEmployees;
    use PayrollReportSupport;

    public static function key(): string
    {
        return 'payroll-cost';
    }

    public function title(): string
    {
        return 'Payroll Cost';
    }

    public function description(): string
    {
        return 'Gross pay, employer contributions and total employer cost by legal entity, department and currency.';
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
        return [...$this->notesCommon(), 'Department is the employee\'s current department.'];
    }

    public function columns(array $filters = []): array
    {
        return ['entity' => 'Legal Entity', 'country' => 'Country', 'department' => 'Department', 'currency' => 'Currency', 'employees' => 'Employees', 'gross' => 'Gross', 'employer_contributions' => 'Employer Contributions', 'employer_cost' => 'Employer Cost'];
    }

    protected function tiebreaker(): string|array
    {
        return ['le.id', 'e.department_id', 're.currency'];
    }

    public function query(array $filters): Builder
    {
        return $this->scopedRuns(DB::table('payroll_run_employees as re')
            ->join('payroll_runs as r', 'r.id', '=', 're.payroll_run_id')
            ->join('payroll_periods as pp', 'pp.id', '=', 'r.payroll_period_id')
            ->join('legal_entities as le', 'le.id', '=', 'r.legal_entity_id')
            ->join('employees as e', 'e.id', '=', 're.employee_id')
            ->where('re.status', 'included'), $filters)
            ->selectRaw('le.id as legal_entity_id, le.name as entity, le.country_code, e.department_id, re.currency, count(distinct re.employee_id) as employees, sum(re.gross_pay) as gross, sum(re.employer_contributions) as employer, sum(re.employer_cost) as cost')
            ->groupBy('le.id', 'le.name', 'le.country_code', 'e.department_id', 're.currency')
            ->orderBy('le.name')
            ->orderBy('le.id')
            ->orderBy('e.department_id')
            ->orderBy('re.currency');
    }

    public function mapRow(mixed $row, array $filters): array
    {
        return [
            'entity' => $row->entity, 'country' => $row->country_code, 'department' => $row->department_id ? $this->tree()->path($row->department_id) : '(none)',
            'currency' => $row->currency, 'employees' => (int) $row->employees, 'gross' => $this->money($row->gross, $row->currency),
            'employer_contributions' => $this->money($row->employer, $row->currency), 'employer_cost' => $this->money($row->cost, $row->currency),
        ];
    }
}

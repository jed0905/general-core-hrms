<?php

namespace App\Services\Reports\Payroll;

use App\Services\Payroll\Support\Decimal;
use App\Services\Reports\Concerns\FiltersEmployees;
use App\Services\Reports\Report;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Statutory contributions per employee and rule: employee share and employer
 * share side by side. Usable as a remittance listing for any country (agency
 * upload file layouts are not generated; see BLOCKERS B4).
 */
class PayrollContributionsReport extends Report
{
    use FiltersEmployees;
    use PayrollReportSupport;

    public static function key(): string
    {
        return 'payroll-statutory-contributions';
    }

    public function title(): string
    {
        return 'Statutory Contributions';
    }

    public function description(): string
    {
        return 'Employee and employer statutory contributions per employee and rule, from finalized payslips.';
    }

    public function filters(): array
    {
        return [
            ...$this->periodFilters(),
            ['key' => 'country_code', 'label' => 'Country', 'type' => 'select', 'options' => DB::table('statutory_rule_versions')->distinct()->orderBy('country_code')->pluck('country_code')->map(fn ($c) => ['value' => $c, 'title' => $c])->all()],
            ['key' => 'rule_key', 'label' => 'Rule', 'type' => 'select', 'options' => DB::table('statutory_rule_versions')->whereIn('category', ['social_insurance', 'healthcare', 'pension', 'employment_insurance', 'statutory_deduction', 'employer_contribution'])->distinct()->orderBy('rule_key')->pluck('rule_key')->map(fn ($k) => ['value' => $k, 'title' => $k])->all()],
        ];
    }

    public function rules(): array
    {
        return [...$this->periodRules(), 'country_code' => ['nullable', 'string', 'size:2'], 'rule_key' => ['nullable', 'string', 'max:80']];
    }

    public function notes(): array
    {
        return [...$this->notesCommon(), 'Employee shares were deducted from pay; employer shares are an employer cost.'];
    }

    public function columns(array $filters = []): array
    {
        return [
            'employee_number' => 'Employee No.', 'employee' => 'Employee', 'country' => 'Country', 'rule_key' => 'Rule',
            'currency' => 'Currency', 'employee_share' => 'Employee Share', 'employer_share' => 'Employer Share', 'total' => 'Total',
        ];
    }

    protected function tiebreaker(): string|array
    {
        return ['e.id', 'sr.rule_key', 'l.currency'];
    }

    public function summary(array $filters): array
    {
        $rows = $this->base($filters)
            ->selectRaw("sr.rule_key, l.currency, sum(case when l.line_class = 'employee_deduction' then l.amount else 0 end) as ee, sum(case when l.line_class = 'employer_contribution' then l.amount else 0 end) as er")
            ->groupBy('sr.rule_key', 'l.currency')->orderBy('sr.rule_key')->get();

        return [[
            'title' => 'Totals by Rule',
            'columns' => ['Rule', 'Currency', 'Employee Share', 'Employer Share'],
            'rows' => $rows->map(fn ($r) => [$r->rule_key, $r->currency, $this->money($r->ee, $r->currency), $this->money($r->er, $r->currency)])->all(),
        ]];
    }

    public function query(array $filters): Builder
    {
        return $this->base($filters)
            ->selectRaw("e.id as employee_id, e.employee_number, e.emp_last_name, e.emp_first_name, sr.country_code, sr.rule_key, l.currency, sum(case when l.line_class = 'employee_deduction' then l.amount else 0 end) as ee, sum(case when l.line_class = 'employer_contribution' then l.amount else 0 end) as er")
            ->groupBy('e.id', 'e.employee_number', 'e.emp_last_name', 'e.emp_first_name', 'sr.country_code', 'sr.rule_key', 'l.currency')
            ->orderBy('e.emp_last_name')
            ->orderBy('e.id')
            ->orderBy('sr.rule_key')
            ->orderBy('l.currency');
    }

    private function base(array $filters): Builder
    {
        return $this->scopedRuns(DB::table('payroll_run_lines as l')
            ->join('statutory_rule_versions as sr', 'sr.id', '=', 'l.statutory_rule_version_id')
            ->join('payroll_runs as r', 'r.id', '=', 'l.payroll_run_id')
            ->join('payroll_periods as pp', 'pp.id', '=', 'r.payroll_period_id')
            ->join('payroll_run_employees as re', 're.id', '=', 'l.payroll_run_employee_id')
            ->join('employees as e', 'e.id', '=', 're.employee_id')
            ->whereIn('l.line_class', ['employee_deduction', 'employer_contribution']), $filters)
            ->when(! empty($filters['country_code']), fn ($q) => $q->where('sr.country_code', $filters['country_code']))
            ->when(! empty($filters['rule_key']), fn ($q) => $q->where('sr.rule_key', $filters['rule_key']));
    }

    public function mapRow(mixed $row, array $filters): array
    {
        $ee = $this->money($row->ee, $row->currency);
        $er = $this->money($row->er, $row->currency);

        return [
            'employee_number' => $row->employee_number, 'employee' => self::personName($row->emp_last_name, $row->emp_first_name),
            'country' => $row->country_code, 'rule_key' => $row->rule_key, 'currency' => $row->currency,
            'employee_share' => $ee, 'employer_share' => $er, 'total' => $this->money(Decimal::add($ee, $er), $row->currency),
        ];
    }
}

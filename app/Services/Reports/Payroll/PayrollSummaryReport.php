<?php

namespace App\Services\Reports\Payroll;

use App\Models\PayrollRun;
use App\Services\Reports\Report;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * One row per payroll run with its stored totals.
 */
class PayrollSummaryReport extends Report
{
    use PayrollReportSupport;

    public static function key(): string
    {
        return 'payroll-summary';
    }

    public function title(): string
    {
        return 'Payroll Summary';
    }

    public function description(): string
    {
        return 'Payroll runs with gross, deductions, tax, net pay and employer cost.';
    }

    public function filters(): array
    {
        return [
            ...$this->periodFilters(),
            ['key' => 'status', 'label' => 'Status', 'type' => 'select', 'default' => PayrollRun::STATUS_FINALIZED, 'options' => collect(PayrollRun::STATUSES)->map(fn ($s) => ['value' => $s, 'title' => ucfirst($s)])->all()],
        ];
    }

    public function rules(): array
    {
        return [...$this->periodRules(), 'status' => ['nullable', Rule::in(PayrollRun::STATUSES)]];
    }

    public function notes(): array
    {
        return ['Choose a status other than finalized to see runs in progress (their figures can still change).', ...array_slice($this->notesCommon(), 1)];
    }

    public function columns(array $filters = []): array
    {
        return [
            'run_number' => 'Run', 'type' => 'Type', 'status' => 'Status', 'entity' => 'Legal Entity', 'pay_group' => 'Pay Group',
            'period' => 'Period', 'pay_date' => 'Pay Date', 'employees' => 'Employees', 'currency' => 'Currency',
            'gross' => 'Gross', 'deductions' => 'Employee Deductions', 'tax' => 'Tax', 'net' => 'Net Pay',
            'employer_contributions' => 'Employer Contributions', 'employer_cost' => 'Employer Cost',
        ];
    }

    public function sortable(): array
    {
        return ['run_number' => 'r.run_number', 'pay_date' => 'pp.pay_date', 'gross' => 'r.gross_total'];
    }

    protected function tiebreaker(): string|array
    {
        return 'r.id';
    }

    public function summary(array $filters): array
    {
        $rows = $this->base($filters)
            ->selectRaw('r.currency, count(*) as runs, sum(r.gross_total) as gross, sum(r.net_total) as net, sum(r.employer_cost_total) as cost')
            ->groupBy('r.currency')->orderBy('r.currency')->get();

        return [[
            'title' => 'Totals by Currency',
            'columns' => ['Currency', 'Runs', 'Gross', 'Net Pay', 'Employer Cost'],
            'rows' => $rows->map(fn ($r) => [$r->currency, (int) $r->runs, $this->money($r->gross, $r->currency), $this->money($r->net, $r->currency), $this->money($r->cost, $r->currency)])->all(),
        ]];
    }

    public function query(array $filters): Builder
    {
        return $this->base($filters)
            ->join('legal_entities as le', 'le.id', '=', 'r.legal_entity_id')
            ->join('pay_groups as pg', 'pg.id', '=', 'r.pay_group_id')
            ->select(['r.*', 'le.name as entity', 'pg.name as pay_group', 'pp.period_start', 'pp.period_end', 'pp.pay_date'])
            ->orderBy('pp.pay_date')
            ->orderBy('r.id');
    }

    private function base(array $filters): Builder
    {
        return $this->scopedRuns(DB::table('payroll_runs as r')->join('payroll_periods as pp', 'pp.id', '=', 'r.payroll_period_id'), $filters, false)
            ->where('r.status', $filters['status'] ?? PayrollRun::STATUS_FINALIZED);
    }

    public function mapRow(mixed $row, array $filters): array
    {
        $m = fn ($v) => $this->money($v, $row->currency);

        return [
            'run_number' => $row->run_number, 'type' => $row->run_type, 'status' => $row->status, 'entity' => $row->entity,
            'pay_group' => $row->pay_group, 'period' => substr($row->period_start, 0, 10).' to '.substr($row->period_end, 0, 10),
            'pay_date' => substr($row->pay_date, 0, 10), 'employees' => (int) $row->employee_count, 'currency' => $row->currency,
            'gross' => $m($row->gross_total), 'deductions' => $m($row->employee_deduction_total), 'tax' => $m($row->tax_total),
            'net' => $m($row->net_total), 'employer_contributions' => $m($row->employer_contribution_total), 'employer_cost' => $m($row->employer_cost_total),
        ];
    }
}

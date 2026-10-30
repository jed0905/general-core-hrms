<?php

namespace App\Services\Reports\Payroll;

use App\Models\PayrollRunLine;
use App\Services\Reports\Report;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Totals per payslip line code (earnings, deductions, taxes, employer contributions).
 */
class PayrollEarningsDeductionsReport extends Report
{
    use PayrollReportSupport;

    public static function key(): string
    {
        return 'payroll-earnings-deductions';
    }

    public function title(): string
    {
        return 'Earnings and Deductions';
    }

    public function description(): string
    {
        return 'Finalized payslip lines totalled by class and code.';
    }

    public function filters(): array
    {
        return [
            ...$this->periodFilters(),
            ['key' => 'line_class', 'label' => 'Line class', 'type' => 'select', 'options' => collect(PayrollRunLine::CLASSES)->map(fn ($c) => ['value' => $c, 'title' => str_replace('_', ' ', ucfirst($c))])->all()],
        ];
    }

    public function rules(): array
    {
        return [...$this->periodRules(), 'line_class' => ['nullable', Rule::in(PayrollRunLine::CLASSES)]];
    }

    public function notes(): array
    {
        return [...$this->notesCommon(), 'Employer contributions are an employer cost and are not part of net pay.'];
    }

    public function columns(array $filters = []): array
    {
        return ['line_class' => 'Class', 'code' => 'Code', 'description' => 'Description', 'currency' => 'Currency', 'employees' => 'Employees', 'lines' => 'Lines', 'amount' => 'Total'];
    }

    protected function tiebreaker(): string|array
    {
        return ['l.line_class', 'l.code', 'l.currency'];
    }

    public function query(array $filters): Builder
    {
        return $this->scopedRuns(DB::table('payroll_run_lines as l')
            ->join('payroll_runs as r', 'r.id', '=', 'l.payroll_run_id')
            ->join('payroll_periods as pp', 'pp.id', '=', 'r.payroll_period_id')
            ->join('payroll_run_employees as re', 're.id', '=', 'l.payroll_run_employee_id'), $filters)
            ->when(! empty($filters['line_class']), fn ($q) => $q->where('l.line_class', $filters['line_class']))
            ->selectRaw('l.line_class, l.code, max(l.description) as description, l.currency, count(distinct re.employee_id) as employees, count(*) as lines, sum(l.amount) as amount')
            ->groupBy('l.line_class', 'l.code', 'l.currency')
            ->orderBy('l.line_class')
            ->orderBy('l.code')
            ->orderBy('l.currency');
    }

    public function mapRow(mixed $row, array $filters): array
    {
        return [
            'line_class' => str_replace('_', ' ', $row->line_class), 'code' => $row->code, 'description' => $row->description,
            'currency' => $row->currency, 'employees' => (int) $row->employees, 'lines' => (int) $row->lines, 'amount' => $this->money($row->amount, $row->currency),
        ];
    }
}

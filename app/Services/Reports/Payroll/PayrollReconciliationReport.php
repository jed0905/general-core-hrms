<?php

namespace App\Services\Reports\Payroll;

use App\Services\Payroll\Support\Decimal;
use App\Services\Reports\Report;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Per finalized run: stored net total vs the sum of payslip nets vs the net
 * recomputed from stored lines vs the amounts disbursed and paid. Any non-zero
 * difference is flagged; nothing is recalculated from configuration.
 */
class PayrollReconciliationReport extends Report
{
    use PayrollReportSupport;

    public static function key(): string
    {
        return 'payroll-reconciliation';
    }

    public function title(): string
    {
        return 'Payroll Reconciliation';
    }

    public function description(): string
    {
        return 'Run totals reconciled against payslips, payslip lines and salary payments.';
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
        return [...$this->notesCommon(), 'Lines net = earnings - employee deductions - employee tax. Reversal runs have no disbursements.'];
    }

    public function columns(array $filters = []): array
    {
        return ['run_number' => 'Run', 'type' => 'Type', 'pay_date' => 'Pay Date', 'currency' => 'Currency', 'run_net' => 'Run Net', 'payslips_net' => 'Payslips Net', 'lines_net' => 'Lines Net', 'disbursed' => 'To Disburse', 'paid' => 'Paid', 'result' => 'Result'];
    }

    protected function tiebreaker(): string|array
    {
        return 'r.id';
    }

    public function query(array $filters): Builder
    {
        $payslips = DB::table('payroll_run_employees')->selectRaw('sum(net_pay)')->whereColumn('payroll_run_id', 'r.id')->where('status', 'included');
        $lines = DB::table('payroll_run_lines')->selectRaw("sum(case when line_class = 'earning' then amount when line_class in ('employee_deduction', 'employee_tax') then -amount else 0 end)")->whereColumn('payroll_run_id', 'r.id');
        $disbursed = DB::table('payroll_disbursements')->selectRaw('sum(amount)')->whereColumn('payroll_run_id', 'r.id');
        $paid = DB::table('payroll_disbursements')->selectRaw('sum(amount)')->whereColumn('payroll_run_id', 'r.id')->where('status', 'paid');

        return $this->scopedRuns(DB::table('payroll_runs as r')->join('payroll_periods as pp', 'pp.id', '=', 'r.payroll_period_id'), $filters)
            ->select(['r.id', 'r.run_number', 'r.run_type', 'r.currency', 'r.net_total', 'pp.pay_date'])
            ->selectSub($payslips, 'payslips_net')
            ->selectSub($lines, 'lines_net')
            ->selectSub($disbursed, 'disbursed')
            ->selectSub($paid, 'paid')
            ->orderBy('pp.pay_date')
            ->orderBy('r.id');
    }

    public function mapRow(mixed $row, array $filters): array
    {
        $m = fn ($v) => $this->money($v, $row->currency);
        [$run, $payslips, $lines] = [$m($row->net_total), $m($row->payslips_net), $m($row->lines_net)];
        $positiveNet = $row->run_type === 'reversal' ? null : $m($row->disbursed);
        $ok = Decimal::cmp($run, $payslips) === 0 && Decimal::cmp($run, $lines) === 0;

        return [
            'run_number' => $row->run_number, 'type' => $row->run_type, 'pay_date' => substr($row->pay_date, 0, 10), 'currency' => $row->currency,
            'run_net' => $run, 'payslips_net' => $payslips, 'lines_net' => $lines, 'disbursed' => $positiveNet ?? '-', 'paid' => $m($row->paid),
            'result' => $ok ? 'Balanced' : 'DIFFERENCE',
        ];
    }
}

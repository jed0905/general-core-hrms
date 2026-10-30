<?php

namespace App\Http\Controllers\Web\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\ExcludeEmployeeRequest;
use App\Http\Requests\Payroll\PayrollRunActionRequest;
use App\Http\Requests\Payroll\StorePayrollRunRequest;
use App\Models\PayGroup;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use App\Services\Payroll\PayrollRunService;
use App\Services\Payroll\PayrollScope;
use App\Services\Payroll\Support\CurrencyPrecision;
use App\Services\Payroll\Support\Decimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Payroll runs: list, create, calculate, include/exclude, review, approve,
 * return, cancel, finalize, reverse and the run register export. Each action is
 * authorized by PayrollRunPolicy and re-checked in PayrollRunService.
 */
class PayrollRunController extends Controller
{
    public function __construct(
        protected PayrollRunService $runs,
        protected PayrollScope $scope,
        protected CurrencyPrecision $precision,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PayrollRun::class);
        $user = $request->user();
        $filters = $request->only(['search', 'status', 'run_type', 'legal_entity_id']);
        $payGroupIds = $this->scope->apply(PayGroup::query()->where('is_active', true), $user)->pluck('id');

        return Inertia::render('app/Payroll/Runs/Index', [
            'runs' => $this->runs->getPaginatedRuns($user, $filters),
            'filters' => $filters,
            'statuses' => PayrollRun::STATUSES,
            'types' => PayrollRun::TYPES,
            'entities' => $this->scope->entityOptions($user),
            'openPeriods' => $user->can('create', PayrollRun::class)
                ? PayrollPeriod::with('payGroup:id,code,name,pay_currency')->whereIn('pay_group_id', $payGroupIds)
                    ->where('status', PayrollPeriod::STATUS_OPEN)->orderBy('pay_date')->limit(200)
                    ->get(['id', 'pay_group_id', 'period_start', 'period_end', 'pay_date'])
                : [],
            'can' => ['create' => $user->can('create', PayrollRun::class)],
        ]);
    }

    public function store(StorePayrollRunRequest $request): RedirectResponse
    {
        $run = $this->runs->createRun($request->validated(), $request->user());

        return redirect()->route('payroll.runs.show', $run)->with('success', "Run {$run->run_number} created.");
    }

    public function show(Request $request, PayrollRun $payrollRun): Response
    {
        $this->authorize('view', $payrollRun);
        $user = $request->user();
        $run = $payrollRun->load([
            'legalEntity:id,code,name,country_code,locale', 'payGroup:id,code,name,pay_frequency,payroll_country_code',
            'period:id,period_start,period_end,pay_date,status', 'reversesRun:id,run_number', 'reversal:id,run_number,status,reverses_run_id',
            'calculator:id,username', 'reviewer:id,username', 'approver:id,username', 'finalizer:id,username',
            'events.actor:id,username',
        ]);
        $places = $this->precision->minorUnits($run->currency);
        $money = fn ($v) => Decimal::format((string) $v, $places);

        $payslips = PayrollRunEmployee::with('employee:id,employee_number,emp_first_name,emp_last_name')
            ->where('payroll_run_id', $run->id)
            ->orderBy('status')
            ->orderBy('employee_id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (PayrollRunEmployee $p) => [
                'id' => $p->id,
                'employment_assignment_id' => $p->employment_assignment_id,
                'employee' => $p->employee,
                'status' => $p->status,
                'exclusion_reason' => $p->exclusion_reason,
                'payslip_number' => $p->payslip_number,
                'gross_pay' => $money($p->gross_pay),
                'deductions' => $money(Decimal::add((string) $p->employee_deductions, (string) $p->tax)),
                'net_pay' => $money($p->net_pay),
                'employer_cost' => $money($p->employer_cost),
            ]);

        return Inertia::render('app/Payroll/Runs/Show', [
            'run' => [
                ...$run->only(['id', 'run_number', 'run_type', 'status', 'currency', 'description', 'uses_unverified_rules', 'engine_version', 'employee_count', 'calculated_at', 'reviewed_at', 'approved_at', 'finalized_at']),
                'legal_entity' => $run->legalEntity,
                'pay_group' => $run->payGroup,
                'period' => $run->period,
                'reverses_run' => $run->reversesRun,
                'reversal' => $run->reversal,
                'actors' => ['calculated' => $run->calculator?->username, 'reviewed' => $run->reviewer?->username, 'approved' => $run->approver?->username, 'finalized' => $run->finalizer?->username],
                'totals' => collect(PayrollRun::TOTAL_COLUMNS)->mapWithKeys(fn ($c) => [$c => $money($run->{$c})]),
                'events' => $run->events,
            ],
            'payslips' => $payslips,
            'exceptions' => $run->exceptions()->with('employee:id,employee_number,emp_first_name,emp_last_name')->orderBy('severity')->limit(500)->get(),
            'excludable' => $user->can('manageEmployees', $run)
                ? $this->runs->eligibleAssignments($run)->whereNotIn('id', PayrollRunEmployee::where('payroll_run_id', $run->id)->where('status', 'excluded')->pluck('employment_assignment_id'))
                    ->map(fn ($a) => ['id' => $a->id, 'label' => $a->employee->employee_number.' · '.$a->employee->emp_last_name.', '.$a->employee->emp_first_name])->values()
                : [],
            'disbursements' => $user->can('disburse', $run)
                ? $run->disbursements()->with('payslip.employee:id,employee_number,emp_first_name,emp_last_name')->orderBy('id')->get()
                    ->map(fn ($d) => [...$d->only(['id', 'method', 'status', 'reference', 'failure_reason', 'paid_at', 'exported_at', 'currency']), 'amount' => $money($d->amount), 'employee' => $d->payslip->employee, 'account' => $d->destination ? ($d->destination['bank_name'].' ••••'.($d->destination['account_last4'] ?? '')) : null])
                : null,
            'can' => [
                'manageEmployees' => $user->can('manageEmployees', $run),
                'calculate' => $user->can('calculate', $run),
                'review' => $user->can('review', $run),
                'approve' => $user->can('approve', $run),
                'returnToDraft' => $user->can('returnToDraft', $run),
                'cancel' => $user->can('cancel', $run),
                'finalize' => $user->can('finalize', $run),
                'reverse' => $user->can('reverse', $run),
                'export' => $user->can('export', $run),
                'disburse' => $user->can('disburse', $run),
                'viewPayslips' => $user->can('payslip.view'),
            ],
        ]);
    }

    public function calculate(PayrollRunActionRequest $request, PayrollRun $payrollRun): RedirectResponse
    {
        $this->authorize('calculate', $payrollRun);
        $run = $this->runs->calculate($payrollRun, $request->user());

        return back()->with('success', "Calculated {$run->employee_count} payslips.");
    }

    public function exclude(ExcludeEmployeeRequest $request, PayrollRun $payrollRun): RedirectResponse
    {
        $this->runs->excludeEmployee($payrollRun, $request->integer('employment_assignment_id'), $request->validated('reason'), $request->user());

        return back()->with('success', 'Employee excluded from this run.');
    }

    public function include(Request $request, PayrollRun $payrollRun, int $assignment): RedirectResponse
    {
        $this->authorize('manageEmployees', $payrollRun);
        $this->runs->includeEmployee($payrollRun, $assignment, $request->user());

        return back()->with('success', 'Employee included again; recalculate the run.');
    }

    public function review(PayrollRunActionRequest $request, PayrollRun $payrollRun): RedirectResponse
    {
        $this->authorize('review', $payrollRun);
        $this->runs->review($payrollRun, $request->user(), $request->validated('remarks'));

        return back()->with('success', 'Run reviewed.');
    }

    public function approve(PayrollRunActionRequest $request, PayrollRun $payrollRun): RedirectResponse
    {
        $this->authorize('approve', $payrollRun);
        $this->runs->approve($payrollRun, $request->user(), $request->validated('remarks'));

        return back()->with('success', 'Run approved.');
    }

    public function returnToDraft(PayrollRunActionRequest $request, PayrollRun $payrollRun): RedirectResponse
    {
        $this->authorize('returnToDraft', $payrollRun);
        $this->runs->returnToDraft($payrollRun, $request->user(), $request->validated('remarks'));

        return back()->with('success', 'Run returned to draft.');
    }

    public function cancel(PayrollRunActionRequest $request, PayrollRun $payrollRun): RedirectResponse
    {
        $this->authorize('cancel', $payrollRun);
        $this->runs->cancel($payrollRun, $request->user(), $request->validated('remarks'));

        return back()->with('success', 'Run cancelled.');
    }

    public function finalize(PayrollRunActionRequest $request, PayrollRun $payrollRun): RedirectResponse
    {
        $this->authorize('finalize', $payrollRun);
        $this->runs->finalize($payrollRun, $request->user(), $request->validated('remarks'));

        return back()->with('success', 'Run finalized. Payslips are now available to employees.');
    }

    public function reverse(PayrollRunActionRequest $request, PayrollRun $payrollRun): RedirectResponse
    {
        $this->authorize('reverse', $payrollRun);
        $reversal = $this->runs->reverse($payrollRun, $request->user(), $request->validated('remarks'));

        return redirect()->route('payroll.runs.show', $reversal)->with('success', "Reversal {$reversal->run_number} created for review.");
    }

    /** Payroll register: one row per payslip line, from stored results. */
    public function export(Request $request, PayrollRun $payrollRun): StreamedResponse
    {
        $this->authorize('export', $payrollRun);
        $places = $this->precision->minorUnits($payrollRun->currency);

        return response()->streamDownload(function () use ($payrollRun, $places) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Run', 'Status', 'Payslip', 'Employee No.', 'Employee', 'Class', 'Code', 'Description', 'Quantity', 'Rate', 'Multiplier', 'Amount', 'Currency', 'Rule version']);
            PayrollRunEmployee::with(['employee:id,employee_number,emp_first_name,emp_last_name', 'lines'])
                ->where('payroll_run_id', $payrollRun->id)
                ->where('status', PayrollRunEmployee::STATUS_INCLUDED)
                ->orderBy('id')
                ->chunkById(200, function ($payslips) use ($out, $payrollRun, $places) {
                    foreach ($payslips as $payslip) {
                        foreach ($payslip->lines as $line) {
                            fputcsv($out, [
                                $payrollRun->run_number, $payrollRun->status, $payslip->payslip_number,
                                $payslip->employee->employee_number, $payslip->employee->emp_last_name.', '.$payslip->employee->emp_first_name,
                                $line->line_class, $line->code, $line->description, $line->quantity, $line->rate, $line->multiplier,
                                Decimal::format((string) $line->amount, $places), $line->currency, $line->statutory_rule_version_id,
                            ]);
                        }
                    }
                });
            fclose($out);
        }, "payroll_register_{$payrollRun->run_number}.csv", ['Content-Type' => 'text/csv']);
    }
}

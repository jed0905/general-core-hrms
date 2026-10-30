<?php

namespace App\Http\Controllers\Web\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\AdjustmentRequest;
use App\Http\Requests\Payroll\ApproveInputsRequest;
use App\Http\Requests\Payroll\ImportTimeInputsRequest;
use App\Http\Requests\Payroll\TimeInputRequest;
use App\Models\PayComponent;
use App\Models\PayGroup;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\PayrollTimeInput;
use App\Services\Payroll\PayrollInputService;
use App\Services\Payroll\PayrollScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Period inputs: time quantities and one-time adjustments, entered by one
 * person and approved by someone holding payroll.input.approve.
 */
class PayrollInputController extends Controller
{
    public function __construct(
        protected PayrollInputService $inputs,
        protected PayrollScope $scope,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $payGroups = $this->scope->apply(PayGroup::query()->where('is_active', true), $user)->orderBy('name')->get(['id', 'code', 'name', 'pay_currency', 'payroll_country_code']);
        $period = $request->integer('payroll_period_id')
            ? PayrollPeriod::with('payGroup')->whereIn('pay_group_id', $payGroups->pluck('id'))->find($request->integer('payroll_period_id'))
            : null;

        return Inertia::render('app/Payroll/Inputs/Index', [
            'payGroups' => $payGroups,
            'periods' => PayrollPeriod::whereIn('pay_group_id', $payGroups->pluck('id'))->where('status', PayrollPeriod::STATUS_OPEN)
                ->orderBy('pay_date')->limit(300)->get(['id', 'pay_group_id', 'period_start', 'period_end', 'pay_date']),
            'period' => $period?->only(['id', 'pay_group_id', 'period_start', 'period_end', 'pay_date', 'status']),
            'data' => $period ? $this->inputs->forPeriod($period) : null,
            'categories' => $period ? $this->inputs->categories($period) : [],
            'components' => PayComponent::where('is_system', false)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'line_class']),
            'can' => ['manage' => $user->can('payroll.input.manage'), 'approve' => $user->can('payroll.input.approve')],
        ]);
    }

    public function storeTimeInput(TimeInputRequest $request, PayrollPeriod $payrollPeriod): RedirectResponse
    {
        $this->inputs->saveTimeInput($payrollPeriod, $request->validated(), $request->user());

        return back()->with('success', 'Time input saved (pending approval).');
    }

    public function importTimeInputs(ImportTimeInputsRequest $request, PayrollPeriod $payrollPeriod): RedirectResponse
    {
        $this->inputs->assertOpen($payrollPeriod, $request->user());
        $result = $this->inputs->importTimeInputs($payrollPeriod, $request->file('file')->get(), $request->user());
        $message = "{$result['saved']} time inputs imported (pending approval).";

        return $result['errors']
            ? back()->with('success', $message)->with('error', implode(' ', array_slice($result['errors'], 0, 10)))
            : back()->with('success', $message);
    }

    public function approveTimeInputs(ApproveInputsRequest $request, PayrollPeriod $payrollPeriod): RedirectResponse
    {
        $count = $this->inputs->approveTimeInputs($payrollPeriod, $request->validated('ids'), $request->user());

        return back()->with('success', "{$count} time inputs approved.");
    }

    public function destroyTimeInput(Request $request, PayrollTimeInput $payrollTimeInput): RedirectResponse
    {
        abort_unless($request->user()->can('payroll.input.manage'), 403);
        $this->inputs->deleteTimeInput($payrollTimeInput, $request->user());

        return back()->with('success', 'Time input removed.');
    }

    public function storeAdjustment(AdjustmentRequest $request, PayrollPeriod $payrollPeriod): RedirectResponse
    {
        $this->inputs->saveAdjustment($payrollPeriod, $request->validated(), $request->user());

        return back()->with('success', 'Adjustment saved (pending approval).');
    }

    public function approveAdjustment(Request $request, PayrollAdjustment $payrollAdjustment): RedirectResponse
    {
        abort_unless($request->user()->can('payroll.input.approve'), 403);
        $this->inputs->assertOpen($payrollAdjustment->period, $request->user());
        $this->inputs->approveAdjustment($payrollAdjustment, $request->user());

        return back()->with('success', 'Adjustment approved.');
    }

    public function destroyAdjustment(Request $request, PayrollAdjustment $payrollAdjustment): RedirectResponse
    {
        abort_unless($request->user()->can('payroll.input.manage'), 403);
        $this->inputs->assertOpen($payrollAdjustment->period, $request->user());
        $this->inputs->deleteAdjustment($payrollAdjustment, $request->user());

        return back()->with('success', 'Adjustment removed.');
    }
}

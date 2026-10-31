<?php

namespace App\Http\Controllers\Web\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\ApproveSummariesRequest;
use App\Http\Requests\Attendance\GenerateSummariesRequest;
use App\Models\AttendancePeriodSummary;
use App\Models\PayrollPeriod;
use App\Services\Attendance\AttendancePeriodService;
use App\Services\Attendance\AttendanceScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Payroll-period attendance summaries: generate, review, approve for payroll. */
class AttendanceSummaryController extends Controller
{
    public function __construct(
        protected AttendancePeriodService $periods,
        protected AttendanceScope $scope,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $periodId = $request->integer('payroll_period_id') ?: null;

        return Inertia::render('app/Attendance/Summaries/Index', [
            'periods' => PayrollPeriod::with('payGroup:id,code,name,time_source')->whereHas('payGroup', fn ($q) => $q->where('time_source', 'attendance'))->orderByDesc('pay_date')->limit(100)->get(['id', 'pay_group_id', 'period_start', 'period_end', 'pay_date', 'status']),
            'selectedPeriodId' => $periodId,
            'summaries' => $periodId
                ? $this->scope->apply(AttendancePeriodSummary::query(), $user)->with(['employee:id,employee_number,emp_first_name,emp_last_name', 'approver:id,username'])->where('payroll_period_id', $periodId)->orderBy('employee_id')->paginate(50)->withQueryString()
                : null,
            'can' => ['generate' => $user->can('attendance.process'), 'approve' => $user->can('attendance.finalize')],
        ]);
    }

    public function generate(GenerateSummariesRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('attendance.scope.all'), 403);
        $count = $this->periods->generate(PayrollPeriod::findOrFail($request->integer('payroll_period_id')), $request->user())->count();

        return back()->with('success', "{$count} summaries generated. Review and approve them for payroll.");
    }

    public function approve(ApproveSummariesRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('attendance.scope.all'), 403);
        $count = $this->periods->approve($request->validated('ids'), $request->user());

        return back()->with('success', "{$count} summaries approved for payroll.");
    }
}

<?php

namespace App\Http\Controllers\Web\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\DecideAdjustmentRequest;
use App\Http\Requests\Attendance\StoreAdjustmentRequest;
use App\Models\AttendanceAdjustment;
use App\Models\Employee;
use App\Services\Attendance\AttendanceAdjustmentService;
use App\Services\Attendance\AttendanceScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Attendance corrections: request (attendance.adjust), decide (attendance.approve_adjustment, not the requester). */
class AttendanceAdjustmentController extends Controller
{
    public function __construct(
        protected AttendanceAdjustmentService $adjustments,
        protected AttendanceScope $scope,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['status', 'employee_id']);
        $filters['status'] ??= 'pending';

        return Inertia::render('app/Attendance/Adjustments/Index', [
            'adjustments' => $this->adjustments->getPaginatedAdjustments($user, $filters)->through(fn (AttendanceAdjustment $a) => [
                ...$a->toArray(),
                'can_decide' => $user->can('decide', $a),
            ]),
            'filters' => $filters,
            'employees' => $this->scope->apply(Employee::query(), $user, 'id')->orderBy('emp_last_name')->get(['id', 'employee_number', 'emp_first_name', 'emp_last_name']),
            'types' => AttendanceAdjustment::TYPES,
            'can' => ['request' => $user->can('attendance.adjust')],
        ]);
    }

    public function store(StoreAdjustmentRequest $request): RedirectResponse
    {
        $this->adjustments->request(Employee::findOrFail($request->integer('employee_id')), $request->validated(), $request->user());

        return back()->with('success', 'Adjustment requested; another authorized user must approve it.');
    }

    public function approve(DecideAdjustmentRequest $request, AttendanceAdjustment $attendanceAdjustment): RedirectResponse
    {
        $this->authorize('decide', $attendanceAdjustment);
        $this->adjustments->decide($attendanceAdjustment, true, $request->user(), $request->validated('remarks'));

        return back()->with('success', 'Adjustment approved; attendance recalculated.');
    }

    public function reject(DecideAdjustmentRequest $request, AttendanceAdjustment $attendanceAdjustment): RedirectResponse
    {
        $this->authorize('decide', $attendanceAdjustment);
        $this->adjustments->decide($attendanceAdjustment, false, $request->user(), $request->validated('remarks'));

        return back()->with('success', 'Adjustment rejected.');
    }
}

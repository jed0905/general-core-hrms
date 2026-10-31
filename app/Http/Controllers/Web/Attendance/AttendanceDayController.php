<?php

namespace App\Http\Controllers\Web\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\AttendanceHistoryRequest;
use App\Http\Requests\Attendance\RecalculateAttendanceRequest;
use App\Models\AttendanceDay;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Shift;
use App\Services\Attendance\AttendanceScope;
use App\Services\Attendance\EmployeeAttendanceService;
use App\Services\Attendance\Engine\AttendanceRecalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daily attendance (filterable; the "exceptions" view is status incomplete /
 * absent / late), one employee's attendance history, and recalculation.
 */
class AttendanceDayController extends Controller
{
    public function __construct(
        protected AttendanceScope $scope,
        protected AttendanceRecalculator $recalculator,
        protected EmployeeAttendanceService $attendance,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['date_from', 'date_to', 'employee_id', 'department_id', 'location_id', 'status', 'shift_id', 'exceptions']);
        $filters['date_from'] ??= now()->toDateString();
        $filters['date_to'] ??= $filters['date_from'];

        $days = $this->scope->apply(AttendanceDay::query(), $user)
            ->with(['employee:id,employee_number,emp_first_name,emp_last_name,department_id', 'shift:id,code,name'])
            ->whereBetween('work_date', [$filters['date_from'], $filters['date_to']])
            ->when($filters['employee_id'] ?? null, fn ($q, $v) => $q->where('employee_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['shift_id'] ?? null, fn ($q, $v) => $q->where('shift_id', $v))
            ->when(! empty($filters['exceptions']), fn ($q) => $q->whereIn('status', ['incomplete', 'absent', 'late']))
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $v)))
            ->when($filters['location_id'] ?? null, fn ($q, $v) => $q->whereHas('employee', fn ($e) => $e->where('location_id', $v)))
            ->orderBy('work_date')
            ->orderBy('employee_id')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('app/Attendance/Daily/Index', [
            'days' => $days,
            'filters' => $filters,
            'employees' => $this->scope->apply(Employee::query(), $user, 'id')->orderBy('emp_last_name')->get(['id', 'employee_number', 'emp_first_name', 'emp_last_name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'locations' => Location::orderBy('city')->get(['id', 'city']),
            'shifts' => Shift::orderBy('name')->get(['id', 'code', 'name']),
            'statuses' => AttendanceDay::STATUSES,
            'can' => ['process' => $user->can('attendance.process'), 'adjust' => $user->can('attendance.adjust')],
        ]);
    }

    public function employee(AttendanceHistoryRequest $request, Employee $employee): Response
    {
        $user = $request->user();
        abort_unless($this->scope->allows($user, $employee->id), 403);
        [$from, $to] = $this->attendance->range($request->validated());

        return Inertia::render('app/Attendance/Daily/Employee', [
            'employee' => $employee->only(['id', 'employee_number', 'emp_first_name', 'emp_last_name']),
            'filters' => ['date_from' => $from, 'date_to' => $to],
            'days' => $this->attendance->days($employee, $from, $to),
            'events' => $this->attendance->events($employee, $from, $to),
            'adjustments' => $this->attendance->adjustments($employee, $from, $to),
            'can' => ['adjust' => $user->can('attendance.adjust'), 'process' => $user->can('attendance.process')],
        ]);
    }

    public function recalculate(RecalculateAttendanceRequest $request): RedirectResponse
    {
        $user = $request->user();
        $ids = $request->validated('employee_ids') ?: $this->scope->apply(Employee::query(), $user, 'id')->where('status', 'active')->pluck('id')->all();
        $ids = array_values(array_filter($ids, fn ($id) => $this->scope->allows($user, (int) $id)));
        if (count($ids) > 2000) {
            throw ValidationException::withMessages(['employee_ids' => 'Recalculate at most 2000 employees at a time.']);
        }

        $count = $this->recalculator->recalculateRange($ids, $request->validated('date_from'), $request->validated('date_to'));

        return back()->with('success', "{$count} attendance days recalculated.");
    }
}

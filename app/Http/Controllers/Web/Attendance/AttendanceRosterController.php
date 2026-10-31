<?php

namespace App\Http\Controllers\Web\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\AssignRosterRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\Shift;
use App\Services\Attendance\AttendanceScope;
use App\Services\Attendance\AttendanceSetupService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Week roster: date-specific shifts / rest days overriding the weekly schedules
 * of the Time module (rotating and multiple shifts).
 */
class AttendanceRosterController extends Controller
{
    public function __construct(
        protected AttendanceSetupService $setup,
        protected AttendanceScope $scope,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $start = CarbonImmutable::parse($request->query('week', now()->toDateString()))->startOfWeek();
        $end = $start->addDays(6);
        $employees = $this->scope->apply(Employee::query(), $user, 'id')->where('status', 'active')
            ->when($request->query('department_id'), fn ($q, $v) => $q->where('department_id', $v))
            ->orderBy('emp_last_name')->paginate(30)->withQueryString();

        return Inertia::render('app/Attendance/Roster/Index', [
            'week' => $start->toDateString(),
            'dates' => collect(range(0, 6))->map(fn ($i) => $start->addDays($i)->toDateString()),
            'employees' => $employees->through(fn ($e) => $e->only(['id', 'employee_number', 'emp_first_name', 'emp_last_name'])),
            'entries' => EmployeeShiftAssignment::with('shift:id,code,name')->whereIn('employee_id', $employees->pluck('id'))->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])->get(),
            'shifts' => Shift::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'start_time', 'end_time']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['department_id']),
            'can' => ['manage' => $user->can('attendance.roster.manage')],
        ]);
    }

    public function store(AssignRosterRequest $request): RedirectResponse
    {
        $user = $request->user();
        $ids = array_values(array_filter($request->validated('employee_ids'), fn ($id) => $this->scope->allows($user, (int) $id)));
        $count = $this->setup->assignRoster($ids, $request->validated('date_from'), $request->validated('date_to'), $request->validated('shift_id'), (bool) $request->validated('is_rest_day'), $request->validated('remarks'), $user);

        return back()->with('success', "{$count} roster days updated; attendance recalculated.");
    }
}

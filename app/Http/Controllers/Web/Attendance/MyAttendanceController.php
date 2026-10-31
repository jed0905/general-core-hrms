<?php

namespace App\Http\Controllers\Web\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\AttendanceHistoryRequest;
use App\Models\Employee;
use App\Services\Attendance\EmployeeAttendanceService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Employee self-service: the logged-in user's own calculated attendance and
 * raw events only. Any employee id in the request is ignored.
 */
class MyAttendanceController extends Controller
{
    public function __construct(protected EmployeeAttendanceService $attendance) {}

    public function index(AttendanceHistoryRequest $request): Response
    {
        $employee = $this->ownEmployee($request);
        [$from, $to] = $this->attendance->range($request->validated());

        if ($employee) {
            $this->authorize('viewAttendance', $employee);
        }

        return Inertia::render('app/Attendance/My/Index', [
            'hasEmployeeRecord' => $employee !== null,
            'hasEnrollment' => $employee !== null && $this->attendance->hasActiveEnrollment($employee),
            'filters' => ['date_from' => $from, 'date_to' => $to],
            'days' => $employee ? $this->attendance->days($employee, $from, $to) : [],
            'events' => $employee ? $this->attendance->events($employee, $from, $to) : [],
            'adjustments' => $employee ? $this->attendance->adjustments($employee, $from, $to)->map->only(['id', 'work_date', 'type', 'status', 'reason', 'created_at']) : [],
            'can' => [
                'export' => $employee !== null && $request->user()->can('exportAttendance', $employee),
            ],
        ]);
    }

    public function export(AttendanceHistoryRequest $request): StreamedResponse
    {
        $employee = $this->ownEmployee($request);

        abort_if($employee === null, 403, 'Your account is not linked to an employee record.');
        $this->authorize('exportAttendance', $employee);
        [$from, $to] = $this->attendance->range($request->validated());

        return $this->attendance->export($employee, $from, $to);
    }

    private function ownEmployee(AttendanceHistoryRequest $request): ?Employee
    {
        $employeeId = $request->user()->employee_id;

        return $employeeId ? Employee::find($employeeId) : null;
    }
}

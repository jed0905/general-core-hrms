<?php

namespace App\Http\Controllers\Web\Time;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceRangeRequest;
use App\Models\Employee;
use App\Services\EmployeeAttendanceService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Employee self-service: the logged-in user's own attendance punches only.
 * Any employee_id in the query string is ignored.
 */
class MyAttendanceController extends Controller
{
    public function __construct(protected EmployeeAttendanceService $attendanceService) {}

    public function index(AttendanceRangeRequest $request): Response
    {
        $employee = $this->ownEmployee($request);
        $filters = $request->safe()->only(['from', 'to']);
        [$from, $to] = $this->attendanceService->range($filters);

        if ($employee) {
            $this->authorize('viewAttendance', $employee);
        }

        return Inertia::render('app/Time/MyAttendance/Index', [
            'hasEmployeeRecord' => $employee !== null,
            'hasBiometricId' => $employee !== null && $this->attendanceService->hasBiometricId($employee),
            'logs' => $employee ? $this->attendanceService->getPaginatedLogs($employee, $filters) : null,
            'filters' => ['from' => $from, 'to' => $to],
            'can' => [
                'export' => $employee !== null && $request->user()->can('exportAttendance', $employee),
            ],
        ]);
    }

    public function export(AttendanceRangeRequest $request): StreamedResponse
    {
        $employee = $this->ownEmployee($request);

        abort_if($employee === null, 403, 'Your account is not linked to an employee record.');
        $this->authorize('exportAttendance', $employee);

        return $this->attendanceService->exportLogs($employee, $request->safe()->only(['from', 'to']));
    }

    private function ownEmployee(AttendanceRangeRequest $request): ?Employee
    {
        $employeeId = $request->user()->employee_id;

        return $employeeId ? Employee::find($employeeId) : null;
    }
}

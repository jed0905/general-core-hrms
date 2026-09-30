<?php

namespace App\Http\Controllers\Web\Time;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\EmployeeWorkScheduleService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Employee self-service: the logged-in user's own schedule assignments only.
 */
class MyScheduleController extends Controller
{
    public function __construct(protected EmployeeWorkScheduleService $scheduleService) {}

    public function index(Request $request): Response
    {
        $employeeId = $request->user()->employee_id;
        $employee = $employeeId ? Employee::find($employeeId) : null;

        if ($employee) {
            $this->authorize('viewWorkSchedule', $employee);
        }

        return Inertia::render('app/Time/MySchedule/Index', [
            'hasEmployeeRecord' => $employee !== null,
            'schedules' => $employee ? $this->scheduleService->getSchedulesForEmployee($employee) : [],
        ]);
    }
}

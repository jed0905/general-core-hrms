<?php

namespace App\Http\Controllers\Web\Time;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeWorkScheduleRequest;
use App\Http\Requests\UpdateEmployeeWorkScheduleRequest;
use App\Models\EmployeeWorkSchedule;
use App\Services\EmployeeWorkScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeWorkScheduleController extends Controller
{
    public function __construct(
        protected EmployeeWorkScheduleService $scheduleService
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'work_schedule_id', 'department_id', 'is_active']);

        return Inertia::render('app/Time/EmployeeSchedules/Index', array_merge(
            [
                'schedules' => $this->scheduleService->getPaginatedSchedules($filters),
                'filters' => $filters,
            ],
            $this->scheduleService->getFormData()
        ));
    }

    public function store(StoreEmployeeWorkScheduleRequest $request): RedirectResponse
    {
        $this->scheduleService->assignSchedules($request->validated());

        return redirect()
            ->route('time.employee-schedules.index')
            ->with('success', 'Work schedule assigned successfully.');
    }

    public function update(UpdateEmployeeWorkScheduleRequest $request, EmployeeWorkSchedule $employeeWorkSchedule): RedirectResponse
    {
        $this->scheduleService->updateSchedule($employeeWorkSchedule, $request->validated());

        return redirect()
            ->route('time.employee-schedules.index')
            ->with('success', 'Employee work schedule updated successfully.');
    }

    public function destroy(EmployeeWorkSchedule $employeeWorkSchedule): RedirectResponse
    {
        $this->scheduleService->removeSchedule($employeeWorkSchedule);

        return redirect()
            ->route('time.employee-schedules.index')
            ->with('success', 'Employee work schedule unassigned successfully.');
    }
}

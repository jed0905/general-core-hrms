<?php

namespace App\Http\Controllers\Web\Time;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkScheduleRequest;
use App\Http\Requests\UpdateWorkScheduleRequest;
use App\Models\Shift;
use App\Models\WorkSchedule;
use App\Services\WorkScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkScheduleController extends Controller
{
    public function __construct(
        protected WorkScheduleService $scheduleService
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status']);
        $schedules = $this->scheduleService->getPaginatedSchedules($filters);

        return Inertia::render('app/Time/WorkSchedules/Index', [
            'schedules' => $schedules,
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        $shifts = Shift::query()
            ->where('is_active', true)
            ->select(['id', 'name', 'code', 'required_hours'])
            ->orderBy('name')
            ->get();

        return Inertia::render('app/Time/WorkSchedules/Create', [
            'shifts' => $shifts,
        ]);
    }

    public function store(StoreWorkScheduleRequest $request): RedirectResponse
    {
        $this->scheduleService->createSchedule($request->validated());

        return redirect()
            ->route('time.work-schedules.index')
            ->with('success', 'Work schedule created successfully.');
    }

    public function edit(WorkSchedule $workSchedule): Response
    {
        $workSchedule->load('days.shift');

        $shifts = Shift::query()
            ->where('is_active', true)
            ->select(['id', 'name', 'code', 'required_hours'])
            ->orderBy('name')
            ->get();

        return Inertia::render('app/Time/WorkSchedules/Edit', [
            'workSchedule' => $workSchedule,
            'shifts' => $shifts,
        ]);
    }

    public function update(UpdateWorkScheduleRequest $request, WorkSchedule $workSchedule): RedirectResponse
    {
        $this->scheduleService->updateSchedule($workSchedule, $request->validated());

        return redirect()
            ->route('time.work-schedules.index')
            ->with('success', 'Work schedule updated successfully.');
    }

    public function destroy(WorkSchedule $workSchedule): RedirectResponse
    {
        $this->scheduleService->archiveSchedule($workSchedule);

        return redirect()
            ->route('time.work-schedules.index')
            ->with('success', 'Work schedule archived successfully.');
    }
}

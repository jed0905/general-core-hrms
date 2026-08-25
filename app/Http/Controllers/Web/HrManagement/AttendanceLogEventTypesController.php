<?php

namespace App\Http\Controllers\Web\HrManagement;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use App\Services\AttendanceLogEventTypesService;
use App\Http\Requests\StoreAttendanceLogEventTypeRequest;
use App\Http\Requests\UpdateAttendanceLogEventTypeRequest;

class AttendanceLogEventTypesController extends Controller
{
    public function __construct(
        protected AttendanceLogEventTypesService $service
    ) {
    }

    public function index()
    {
        $eventTypes = $this->service->all();

        return Inertia::render('app/HrManagement/DailyTimeRecord/AttendanceLogsEventTypes/Index', [
            'eventTypes' => $eventTypes,
        ]);
    }

    public function store(StoreAttendanceLogEventTypeRequest $request)
    {
        $validated = $request->validated();
        $this->service->create($validated);

        return redirect()
            ->route('hrmanagement.dailytimerecord.attendanceLogEventTypes.index')
            ->with('success', 'Attendance log event type created.');
    }

    public function update(UpdateAttendanceLogEventTypeRequest $request, $id)
    {
        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active', true);

        $this->service->update((int) $id, $validated);

        return redirect()
            ->route('hrmanagement.dailytimerecord.attendanceLogEventTypes.index')
            ->with('success', 'Attendance log event type updated.');
    }

    public function destroy($id)
    {
        $this->service->delete((int) $id);

        return redirect()
            ->route('hrmanagement.dailytimerecord.attendanceLogEventTypes.index')
            ->with('success', 'Attendance log event type deleted.');
    }
}

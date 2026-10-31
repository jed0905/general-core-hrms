<?php

namespace App\Http\Controllers\Web\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\ImportEventsRequest;
use App\Http\Requests\Attendance\StoreManualEventRequest;
use App\Models\AttendanceDevice;
use App\Models\AttendanceEvent;
use App\Models\Department;
use App\Models\Employee;
use App\Services\Attendance\AttendanceEventEntryService;
use App\Services\Attendance\AttendanceScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Raw logs (server-side paginated; never all loaded), manual punches and CSV
 * imports. Payloads and device credentials are never shown.
 */
class AttendanceLogController extends Controller
{
    public function __construct(
        protected AttendanceEventEntryService $entries,
        protected AttendanceScope $scope,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['date_from', 'date_to', 'device_id', 'employee_id', 'source', 'status', 'department_id']);
        $filters['date_from'] ??= now()->subDays(7)->toDateString();
        $filters['date_to'] ??= now()->toDateString();
        $user = $request->user();

        return Inertia::render('app/Attendance/Logs/Index', [
            'events' => $this->entries->getPaginatedEvents($user, $filters),
            'filters' => $filters,
            'devices' => AttendanceDevice::orderBy('name')->get(['id', 'name', 'vendor']),
            'employees' => $this->scope->apply(Employee::query(), $user, 'id')->orderBy('emp_last_name')->get(['id', 'employee_number', 'emp_first_name', 'emp_last_name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'sources' => AttendanceEvent::SOURCES,
            'types' => AttendanceEvent::TYPES,
            'can' => ['create' => $user->can('attendance.create'), 'import' => $user->can('attendance.log.import')],
        ]);
    }

    public function store(StoreManualEventRequest $request): RedirectResponse
    {
        $result = $this->entries->createManual(Employee::findOrFail($request->integer('employee_id')), $request->validated(), $request->user());

        return back()->with('success', $result->inserted ? 'Punch recorded.' : 'An identical punch already exists.');
    }

    public function import(ImportEventsRequest $request): RedirectResponse
    {
        $result = $this->entries->importCsv($request->file('file')->get(), $request->user());
        $response = back()->with('success', "{$result['inserted']} punches imported, {$result['duplicates']} already stored.");

        return $result['errors'] ? $response->with('error', implode(' ', array_slice($result['errors'], 0, 10))) : $response;
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(fn () => print (AttendanceEventEntryService::template()), 'attendance_import_template.csv', ['Content-Type' => 'text/csv']);
    }
}

<?php

namespace App\Http\Controllers\Web\Time;

use App\Http\Controllers\Controller;
use App\Http\Requests\TimeLogFilterRequest;
use App\Services\AttendanceLogService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HR view of raw device punches across the organization. This is not a DTR:
 * nothing is computed from schedules (late, absent, overtime, ...).
 */
class TimeLogController extends Controller
{
    public function __construct(protected AttendanceLogService $attendanceLogs) {}

    public function index(TimeLogFilterRequest $request): Response
    {
        $schema = $this->attendanceLogs->filters();
        $filters = $request->filters($schema);
        $user = $request->user();

        return Inertia::render('app/Time/TimeLogs/Index', [
            'logs' => $this->attendanceLogs->query($filters)
                ->paginate($request->perPage())
                ->withQueryString()
                ->through(fn ($row) => $this->attendanceLogs->mapRow($row)),
            'filters' => $filters,
            'filterSchema' => $schema,
            'perPageOptions' => TimeLogFilterRequest::PER_PAGE_OPTIONS,
            'can' => [
                // Exports reuse Reports → Attendance Log (same query and filters).
                'export' => $user->can('report.attendance') && $user->can('report.export'),
            ],
        ]);
    }
}

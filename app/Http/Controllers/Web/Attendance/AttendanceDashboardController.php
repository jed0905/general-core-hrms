<?php

namespace App\Http\Controllers\Web\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceAdjustment;
use App\Models\AttendanceDay;
use App\Models\AttendanceDevice;
use App\Models\AttendanceEvent;
use App\Services\Attendance\AttendanceScope;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Today's attendance at a glance, device health and work queues (within the user's scope). */
class AttendanceDashboardController extends Controller
{
    public function __construct(protected AttendanceScope $scope) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $date = $request->query('date', now()->toDateString());
        $days = $this->scope->apply(AttendanceDay::query(), $user)->whereDate('work_date', $date);

        return Inertia::render('app/Attendance/Dashboard/Index', [
            'date' => $date,
            'statusCounts' => (clone $days)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'queues' => [
                'adjustments_pending' => $this->scope->apply(AttendanceAdjustment::query(), $user)->where('status', 'pending')->count(),
                'incomplete_days' => $this->scope->apply(AttendanceDay::query(), $user)->where('status', 'incomplete')->where('work_date', '>=', now()->subDays(31)->toDateString())->count(),
                'unmatched_events' => $user->can('attendance.scope.all') ? AttendanceEvent::where('status', 'unmatched')->count() : null,
            ],
            'devices' => $user->can('attendance.device.view')
                ? AttendanceDevice::where('is_active', true)->orderBy('name')->get()->map(fn ($d) => $d->only(['id', 'name', 'vendor', 'status', 'last_seen_at', 'last_success_at', 'last_error', 'connection_method']))
                : null,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Web\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreDeviceRequest;
use App\Http\Requests\Attendance\UpdateDeviceConnectionRequest;
use App\Http\Requests\Attendance\UpdateDeviceRequest;
use App\Models\AttendanceConnector;
use App\Models\AttendanceDevice;
use App\Models\AttendanceEvent;
use App\Models\LegalEntity;
use App\Models\Location;
use App\Services\Attendance\AttendanceDeviceService;
use App\Services\Attendance\Devices\DeviceAdapterRegistry;
use App\Services\Attendance\DeviceSyncService;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Biometric device administration: register, edit, connection settings and
 * credentials (attendance.device.configure), push token, activate, test and
 * synchronize. Credentials and token hashes never reach the page.
 */
class AttendanceDeviceController extends Controller
{
    public function __construct(
        protected AttendanceDeviceService $devices,
        protected DeviceSyncService $sync,
        protected DeviceAdapterRegistry $adapters,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'vendor', 'status', 'location_id']);
        $user = $request->user();

        return Inertia::render('app/Attendance/Devices/Index', [
            'devices' => $this->devices->getPaginatedDevices($filters),
            'filters' => $filters,
            'options' => $this->options(),
            'can' => [
                'manage' => $user->can('attendance.device.manage'),
                'configure' => $user->can('attendance.device.configure'),
            ],
        ]);
    }

    public function store(StoreDeviceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $device = $this->devices->create(
            array_intersect_key($data, array_flip(AttendanceDeviceService::DETAIL_FIELDS)),
            array_intersect_key($data, array_flip([...AttendanceDeviceService::CONNECTION_FIELDS, 'username', 'password'])),
            $request->user()
        );

        return redirect()->route('attendance.devices.show', $device)->with('success', 'Device registered.');
    }

    public function show(Request $request, AttendanceDevice $attendanceDevice): Response
    {
        $user = $request->user();
        $device = $attendanceDevice->load(['location:id,city,address', 'legalEntity:id,code,name', 'connector:id,name,key,last_seen_at']);

        return Inertia::render('app/Attendance/Devices/Show', [
            'device' => [...$device->toSafeArray(), 'location' => $device->location, 'legal_entity' => $device->legalEntity, 'connector' => $device->connector],
            'syncRuns' => $device->syncRuns()->with('triggeredBy:id,username')->latest('id')->paginate(15)->withQueryString(),
            'stats' => [
                'events_total' => AttendanceEvent::where('attendance_device_id', $device->id)->count(),
                'events_today' => AttendanceEvent::where('attendance_device_id', $device->id)->where('received_at', '>=', now()->startOfDay())->count(),
                'unmatched' => AttendanceEvent::where('attendance_device_id', $device->id)->where('status', 'unmatched')->count(),
                'invalid' => AttendanceEvent::where('attendance_device_id', $device->id)->where('status', 'invalid')->count(),
                'enrollments_active' => $device->enrollments()->where('status', 'active')->count(),
                'by_source' => AttendanceEvent::where('attendance_device_id', $device->id)->select('source', DB::raw('count(*) as total'))->groupBy('source')->pluck('total', 'source'),
            ],
            'pushUrl' => match ($device->connection_method) {
                'isapi_push', 'isapi_pull' => url('/attendance/push/hikvision/').'/{token}',
                'zk_adms' => url('/'),
                default => null,
            },
            'newPushToken' => session('push_token'),
            'options' => $this->options(),
            'can' => [
                'manage' => $user->can('attendance.device.manage'),
                'configure' => $user->can('attendance.device.configure'),
                'sync' => $user->can('attendance.device.sync'),
                'viewLogs' => $user->can('attendance.log.view'),
            ],
        ]);
    }

    public function update(UpdateDeviceRequest $request, AttendanceDevice $attendanceDevice): RedirectResponse
    {
        $this->devices->updateDetails($attendanceDevice, $request->validated(), $request->user());

        return back()->with('success', 'Device saved.');
    }

    public function updateConnection(UpdateDeviceConnectionRequest $request, AttendanceDevice $attendanceDevice): RedirectResponse
    {
        $this->devices->updateConnection($attendanceDevice, $request->validated(), $request->user());

        return back()->with('success', 'Connection settings saved.');
    }

    public function rotatePushToken(Request $request, AttendanceDevice $attendanceDevice): RedirectResponse
    {
        $token = $this->devices->rotatePushToken($attendanceDevice, $request->user());

        // Shown once on the next page; only its hash is stored.
        return back()->with('push_token', $token)->with('success', 'New push token created. Copy it now; it will not be shown again.');
    }

    public function toggle(Request $request, AttendanceDevice $attendanceDevice): RedirectResponse
    {
        $this->devices->setActive($attendanceDevice, ! $attendanceDevice->is_active, $request->user());

        return back()->with('success', $attendanceDevice->is_active ? 'Device activated.' : 'Device deactivated.');
    }

    public function test(Request $request, AttendanceDevice $attendanceDevice): RedirectResponse
    {
        $run = $this->sync->testConnection($attendanceDevice, $request->user());

        return $run->status === 'failed'
            ? back()->with('error', 'Connection test failed: '.$run->error_message)
            : back()->with('success', $run->error_message ?? 'Connection test succeeded.');
    }

    public function sync(Request $request, AttendanceDevice $attendanceDevice): RedirectResponse
    {
        $run = $this->sync->sync($attendanceDevice, 'manual', $request->user());

        return $run->status === 'failed'
            ? back()->with('error', 'Synchronization failed: '.$run->error_message)
            : back()->with('success', "Synchronized: {$run->inserted} new, {$run->duplicates} already stored, {$run->unmatched} unmapped.");
    }

    private function options(): array
    {
        return [
            'vendors' => $this->adapters->options(),
            'locations' => Location::orderBy('city')->get(['id', 'city', 'address']),
            'legalEntities' => LegalEntity::orderBy('name')->get(['id', 'code', 'name', 'timezone']),
            'connectors' => AttendanceConnector::orderBy('name')->get(['id', 'name']),
            'timezones' => DateTimeZone::listIdentifiers(),
        ];
    }
}

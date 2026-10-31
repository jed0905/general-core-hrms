<?php

namespace App\Http\Controllers\Web\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\AttendancePolicyRequest;
use App\Models\AttendanceConnector;
use App\Models\AttendancePolicy;
use App\Models\LegalEntity;
use App\Models\Location;
use App\Services\Attendance\AttendanceSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Attendance policies and on-premises connectors. */
class AttendanceSettingsController extends Controller
{
    public function __construct(protected AttendanceSetupService $setup) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('app/Attendance/Settings/Index', [
            'policies' => AttendancePolicy::with('legalEntity:id,code,name')->orderByDesc('is_default')->orderBy('name')->get(),
            'connectors' => $user->can('attendance.device.configure') ? AttendanceConnector::withCount('devices')->orderBy('name')->get() : [],
            'legalEntities' => LegalEntity::orderBy('name')->get(['id', 'code', 'name']),
            'locations' => Location::orderBy('city')->get(['id', 'city']),
            'newConnectorSecret' => session('connector_secret'),
            'connectorEndpoint' => route('attendance.device.connector.events'),
            'can' => ['config' => $user->can('attendance.config.manage'), 'connectors' => $user->can('attendance.device.configure')],
        ]);
    }

    public function storePolicy(AttendancePolicyRequest $request): RedirectResponse
    {
        $this->setup->savePolicy($request->validated(), $request->user());

        return back()->with('success', 'Policy saved.');
    }

    public function updatePolicy(AttendancePolicyRequest $request, AttendancePolicy $attendancePolicy): RedirectResponse
    {
        $this->setup->savePolicy($request->validated(), $request->user(), $attendancePolicy);

        return back()->with('success', 'Policy saved. Recalculate attendance for dates it should affect.');
    }
}

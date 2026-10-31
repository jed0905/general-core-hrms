<?php

namespace App\Http\Controllers\Web\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreConnectorRequest;
use App\Models\AttendanceConnector;
use App\Services\Attendance\AttendanceDeviceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** On-premises connectors; their secrets are shown once after creation or rotation. */
class AttendanceConnectorController extends Controller
{
    public function __construct(protected AttendanceDeviceService $devices) {}

    public function store(StoreConnectorRequest $request): RedirectResponse
    {
        [$connector, $secret] = $this->devices->createConnector($request->validated(), $request->user());

        return back()->with('connector_secret', ['key' => $connector->key, 'secret' => $secret])->with('success', 'Connector created. Copy its key and secret now; the secret will not be shown again.');
    }

    public function rotate(Request $request, AttendanceConnector $attendanceConnector): RedirectResponse
    {
        $secret = $this->devices->rotateConnectorSecret($attendanceConnector, $request->user());

        return back()->with('connector_secret', ['key' => $attendanceConnector->key, 'secret' => $secret])->with('success', 'Connector secret rotated. Update the connector configuration now.');
    }

    public function toggle(Request $request, AttendanceConnector $attendanceConnector): RedirectResponse
    {
        $this->devices->setConnectorActive($attendanceConnector, ! $attendanceConnector->is_active, $request->user());

        return back()->with('success', 'Connector updated.');
    }
}

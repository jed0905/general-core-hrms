<?php

namespace App\Http\Controllers\Device;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDevice;
use App\Services\Attendance\Devices\Adapters\ZktecoAdmsAdapter;
use App\Services\Attendance\Devices\DeviceAdapterRegistry;
use App\Services\Attendance\DeviceSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * ZKTeco PUSH/ADMS endpoints (fixed by device firmware):
 *   GET  /iclock/cdata?SN=..&options=all   handshake (returns upload options)
 *   POST /iclock/cdata?SN=..&table=ATTLOG  attendance upload
 *   GET  /iclock/getrequest?SN=..          command poll / heartbeat (no commands are sent)
 *   POST /iclock/devicecmd?SN=..           command results (acknowledged)
 * Only registered, active zkteco devices with method zk_adms are accepted
 * (identified by serial number). The protocol has no request signature, so
 * deployments must restrict the endpoint (HTTPS, allow-listed source
 * addresses / VPN); see NETWORK_TOPOLOGIES.md.
 */
class ZktecoPushController extends Controller
{
    public function __construct(
        protected DeviceAdapterRegistry $adapters,
        protected DeviceSyncService $sync,
    ) {}

    public function cdata(Request $request): Response
    {
        $device = $this->device($request);
        if (! $device) {
            return $this->text('Unknown device', 403);
        }

        /** @var ZktecoAdmsAdapter $adapter */
        $adapter = $this->adapters->for($device);

        if ($request->isMethod('get')) {
            $this->sync->heartbeat($device);

            return $this->text($adapter->handshake($device));
        }

        $events = $adapter->parsePush($device, $request);
        if ($events) {
            $this->sync->receivePush($device, $events);
        } else {
            $this->sync->heartbeat($device);
        }

        return $this->text('OK: '.count($events));
    }

    public function getRequest(Request $request): Response
    {
        $device = $this->device($request);
        if (! $device) {
            return $this->text('Unknown device', 403);
        }
        $this->sync->heartbeat($device);

        return $this->text('OK');
    }

    public function deviceCommand(Request $request): Response
    {
        return $this->device($request) ? $this->text('OK') : $this->text('Unknown device', 403);
    }

    private function device(Request $request): ?AttendanceDevice
    {
        $serial = (string) $request->query('SN', '');

        return $serial === '' ? null : AttendanceDevice::where('vendor', 'zkteco')
            ->where('serial_number', $serial)
            ->where('connection_method', 'zk_adms')
            ->where('is_active', true)
            ->first();
    }

    private function text(string $body, int $status = 200): Response
    {
        return response($body, $status)->header('Content-Type', 'text/plain');
    }
}

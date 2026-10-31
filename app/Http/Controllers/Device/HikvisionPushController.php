<?php

namespace App\Http\Controllers\Device;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDevice;
use App\Services\Attendance\Devices\DeviceAdapterRegistry;
use App\Services\Attendance\Devices\Support\DeviceCommunicationException;
use App\Services\Attendance\DeviceStreamService;
use App\Services\Attendance\DeviceSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Hikvision HTTP host notification ("HTTP listening") push. The device posts
 * multipart/form-data with an AccessControllerEvent JSON part to
 * /attendance/push/hikvision/{token}; the token is a per-device secret stored
 * only as a SHA-256 hash. A device registered for polling may push as well
 * (push, alertStream and polling share the dedupe key, so each event is stored
 * once). When the device host is an IP address, requests must come from it.
 * Notifications go through DeviceStreamService (the adapter's shared
 * classification, the history guard and DeviceSyncService::receivePush).
 *
 * Acknowledgement (verified on a DS-K1T321MFX): any prompt 2xx makes the device
 * send its next event; a non-2xx makes it re-send the same event about once a
 * second. So 200 is returned only after the event is stored, recognized as a
 * duplicate or deliberately skipped (history, heartbeat, other event codes);
 * an exception becomes a 500 and the device retries. Stages are recorded on
 * the request for LogDevicePushRequests, which logs the status actually sent.
 */
class HikvisionPushController extends Controller
{
    public function __construct(
        protected DeviceAdapterRegistry $adapters,
        protected DeviceSyncService $sync,
        protected DeviceStreamService $notifications,
    ) {}

    public function __invoke(Request $request, string $token): Response
    {
        $device = AttendanceDevice::where('push_token_hash', hash('sha256', $token))
            ->where('vendor', 'hikvision')
            ->whereIn('connection_method', ['isapi_push', 'isapi_pull'])
            ->where('is_active', true)
            ->first();

        if (! $device) {
            return $this->reject($request, 401, 'unknown token');
        }
        $request->attributes->set('push.device', $device->id);

        if (filter_var($device->host, FILTER_VALIDATE_IP) && $request->ip() !== $device->host) {
            return $this->reject($request, 403, 'source address is not the device');
        }

        try {
            $notification = $this->adapters->for($device)->pushNotification($request);
        } catch (DeviceCommunicationException) {
            return $this->reject($request, 400, 'unreadable payload');
        }

        $access = $notification['AccessControllerEvent'] ?? [];
        $request->attributes->add([
            'push.parsed' => true,
            'push.serial' => $access['serialNo'] ?? ($notification['eventType'] ?? '-'),
            'push.event_time' => $notification['dateTime'] ?? '-',
        ]);

        $outcome = $this->notifications->handle($device, $notification, 'push');
        if ($outcome === 'ignored') {
            $this->sync->heartbeat($device);
        }
        $request->attributes->set('push.outcome', $outcome);

        return $this->acknowledge();
    }

    public static function logPath(): string
    {
        return storage_path(app()->runningUnitTests() ? 'logs/attendance-push-testing.log' : 'logs/attendance-push.log');
    }

    /** ISAPI-style success body (accepted by the device), with an explicit length. */
    private function acknowledge(): Response
    {
        $body = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<ResponseStatus version="2.0" xmlns="http://www.isapi.org/ver20/XMLSchema"><statusCode>1</statusCode><statusString>OK</statusString><subStatusCode>ok</subStatusCode></ResponseStatus>';

        return response($body, 200, ['Content-Type' => 'application/xml; charset="UTF-8"', 'Content-Length' => (string) strlen($body)]);
    }

    private function reject(Request $request, int $status, string $reason): Response
    {
        $request->attributes->set('push.outcome', 'rejected: '.$reason);

        return response('Rejected', $status, ['Content-Length' => '8']);
    }
}

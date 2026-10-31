<?php

namespace App\Http\Controllers\Device;

use App\Http\Controllers\Controller;
use App\Models\AttendanceConnector;
use App\Models\AttendanceDevice;
use App\Services\Attendance\Devices\Support\EventTime;
use App\Services\Attendance\Devices\Support\NormalizedEvent;
use App\Services\Attendance\DeviceSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Event batches from an HRMS on-premises connector.
 *
 * Authentication: headers X-Connector-Key, X-Timestamp (unix seconds),
 * X-Nonce and X-Signature = hex HMAC-SHA256(secret, timestamp."\n".nonce."\n".body).
 * The timestamp must be within the replay window and each nonce is accepted
 * once. A connector may only submit events for devices assigned to it.
 * Body: {"batches":[{"device_id":1,"events":[{"user":"7","time":"...","type":"check_in","id":"123","verify":"face"}]}]}
 */
class ConnectorEventController extends Controller
{
    public function __construct(protected DeviceSyncService $sync) {}

    public function __invoke(Request $request): JsonResponse
    {
        $connector = $this->authenticate($request);
        if (! $connector) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $connector->forceFill(['last_seen_at' => now(), 'last_seen_ip' => $request->ip()])->save();
        $results = [];

        foreach ((array) $request->json('batches', []) as $batch) {
            $device = AttendanceDevice::where('id', (int) ($batch['device_id'] ?? 0))
                ->where('attendance_connector_id', $connector->id)
                ->where('connection_method', 'connector')
                ->where('is_active', true)
                ->first();

            if (! $device) {
                $results[] = ['device_id' => $batch['device_id'] ?? null, 'error' => 'unknown_device'];

                continue;
            }

            $events = array_map(fn (array $e) => new NormalizedEvent(
                deviceUserId: isset($e['user']) ? (string) $e['user'] : null,
                deviceEventId: isset($e['id']) ? (string) $e['id'] : null,
                eventTimeRaw: (string) ($e['time'] ?? ''),
                occurredAt: EventTime::toUtc((string) ($e['time'] ?? ''), $device->timezone),
                eventType: (string) ($e['type'] ?? 'unknown'),
                verifyMode: isset($e['verify']) ? (string) $e['verify'] : null,
                payload: ['via' => 'connector'],
                invalidReason: EventTime::toUtc((string) ($e['time'] ?? ''), $device->timezone) ? null : 'Unparseable or implausible device timestamp.',
            ), array_filter((array) ($batch['events'] ?? []), 'is_array'));

            $result = $this->sync->receivePush($device, $events, 'connector');
            $results[] = ['device_id' => $device->id, 'inserted' => $result->inserted, 'duplicates' => $result->duplicates, 'unmatched' => $result->unmatched, 'invalid' => $result->invalid];
        }

        return response()->json(['results' => $results]);
    }

    private function authenticate(Request $request): ?AttendanceConnector
    {
        $key = (string) $request->header('X-Connector-Key');
        $timestamp = (string) $request->header('X-Timestamp');
        $nonce = (string) $request->header('X-Nonce');
        $signature = (string) $request->header('X-Signature');
        $window = (int) config('attendance.push.replay_window_seconds', 300);

        if ($key === '' || ! ctype_digit($timestamp) || strlen($nonce) < 16 || abs(time() - (int) $timestamp) > $window) {
            return null;
        }

        $connector = AttendanceConnector::where('key', $key)->where('is_active', true)->first();
        if (! $connector) {
            return null;
        }

        $expected = hash_hmac('sha256', $timestamp."\n".$nonce."\n".$request->getContent(), (string) $connector->secret);
        if (! hash_equals($expected, $signature)) {
            return null;
        }

        // Each nonce once (replay protection), remembered for longer than the window.
        return Cache::add("attendance-connector-nonce:{$connector->id}:{$nonce}", true, $window * 2) ? $connector : null;
    }
}

<?php

namespace App\Services\Attendance\Devices\Adapters;

use App\Models\AttendanceDevice;
use App\Services\Attendance\Devices\Contracts\DeviceAdapter;
use App\Services\Attendance\Devices\Support\ConnectionResult;
use App\Services\Attendance\Devices\Support\DeviceCommunicationException;
use App\Services\Attendance\Devices\Support\EventTime;
use App\Services\Attendance\Devices\Support\FetchResult;
use App\Services\Attendance\Devices\Support\NormalizedEvent;
use App\Services\Attendance\Devices\Support\UnsupportedDeviceOperation;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Hikvision access-control terminals over ISAPI (HTTP, digest authentication).
 *
 * Request shapes follow the integration tested against the customer's
 * DS-K1T321 (branch feature/hikvision-api, commit 3203d23):
 *   GET  /ISAPI/System/deviceInfo?format=json                 -> DeviceInfo{model, serialNumber, firmwareVersion}
 *   POST /ISAPI/AccessControl/AcsEvent?format=json             -> AcsEvent{responseStatusStrg, numOfMatches, InfoList[]}
 *        AcsEventCond{searchID, searchResultPosition, maxResults, major, minor, startTime, endTime}
 *   push (HTTP listening): multipart field "AccessControllerEvent" (or "event_log") with JSON.
 * deviceInfo and time may answer without a JSON section even with ?format=json
 * (a DS-K1T321MFX on firmware V4.61.0 build 260828 did for deviceInfo), so XML
 * answers are accepted too.
 * Pagination (MORE / NO MORE) and /ISAPI/System/time follow public community
 * usage; full ISAPI documentation is partner-gated, so endpoints may differ by
 * model and firmware (see docs/development/attendance/HIKVISION_DS_K1T321.md).
 * Personal names, card numbers and pictures in device records are not stored.
 */
class HikvisionIsapiAdapter implements DeviceAdapter
{
    /** Tested event filter: major 5 (event), minor 38. Override per device in settings.acs_event_minors. */
    public const DEFAULT_MAJOR = 5;

    public const DEFAULT_MINORS = [38];

    private const MAX_PAGES = 500;

    private const STATUS_MAP = [
        'checkIn' => 'check_in',
        'checkOut' => 'check_out',
        'breakIn' => 'break_in',
        'breakOut' => 'break_out',
        'overtimeIn' => 'overtime_in',
        'overtimeOut' => 'overtime_out',
    ];

    public function vendor(): string
    {
        return 'hikvision';
    }

    public function label(): string
    {
        return 'Hikvision (ISAPI)';
    }

    public function connectionMethods(): array
    {
        return [
            'isapi_pull' => 'HRMS polls the device (ISAPI)',
            'isapi_push' => 'Device pushes events to the HRMS (HTTP listening)',
            'connector' => 'On-premises HRMS connector',
        ];
    }

    public function capabilities(AttendanceDevice $device): array
    {
        return match ($device->connection_method) {
            'isapi_pull' => [self::CAP_TEST_CONNECTION, self::CAP_DEVICE_INFO, self::CAP_FETCH_EVENTS, self::CAP_INCREMENTAL, self::CAP_CLOCK_CHECK],
            'isapi_push' => [self::CAP_PUSH],
            default => [],
        };
    }

    public function testConnection(AttendanceDevice $device): ConnectionResult
    {
        $this->assertPull($device, 'Connection test');
        $info = $this->json($this->request($device)->get($device->baseUrl().'/ISAPI/System/deviceInfo', ['format' => 'json']), 'DeviceInfo');

        return new ConnectionResult(
            ok: true,
            model: $info['model'] ?? null,
            serialNumber: $info['serialNumber'] ?? null,
            firmware: trim(($info['firmwareVersion'] ?? '').' '.($info['firmwareReleasedDate'] ?? '')) ?: null,
            clockDriftSeconds: $this->clockDrift($device),
        );
    }

    public function fetchEvents(AttendanceDevice $device, CarbonImmutable $from, CarbonImmutable $to, ?callable $onPage = null): FetchResult
    {
        $this->assertPull($device, 'Event retrieval');

        $events = [];
        $pages = 0;
        $complete = true;

        foreach ($this->minors($device) as $minor) {
            $searchId = (string) Str::uuid();
            $position = 0;

            do {
                $pages++;
                $result = $this->json($this->request($device)->post($device->baseUrl().'/ISAPI/AccessControl/AcsEvent?format=json', [
                    'AcsEventCond' => [
                        'searchID' => $searchId,
                        'searchResultPosition' => $position,
                        'maxResults' => max(1, (int) $device->page_size),
                        'major' => (int) ($device->settings['acs_event_major'] ?? self::DEFAULT_MAJOR),
                        'minor' => (int) $minor,
                        'startTime' => $from->setTimezone($device->timezone)->toIso8601String(),
                        'endTime' => $to->setTimezone($device->timezone)->toIso8601String(),
                    ],
                ]), 'AcsEvent');

                $list = $result['InfoList'] ?? [];
                if (isset($list['serialNo'])) {
                    $list = [$list]; // single record returned as an object
                }

                $page = [];
                foreach ($list as $record) {
                    if ($event = $this->normalize($device, $record)) {
                        $page[] = $event;
                    }
                }
                if ($onPage) {
                    $onPage($page); // ingested now; nothing accumulates across pages
                } else {
                    array_push($events, ...$page);
                }

                $count = (int) ($result['numOfMatches'] ?? count($list));
                $position += $count;
                $more = strtoupper((string) ($result['responseStatusStrg'] ?? '')) === 'MORE' && $count > 0;
                unset($result, $list, $page);
            } while ($more && $pages < self::MAX_PAGES);

            $complete = $complete && ! $more;
        }

        return new FetchResult($events, $complete, $pages);
    }

    public function parsePush(AttendanceDevice $device, Request $request): array
    {
        if (! in_array($device->connection_method, ['isapi_push', 'isapi_pull'], true)) {
            throw UnsupportedDeviceOperation::for('hikvision', $device->connection_method, 'Device push');
        }

        return ($event = $this->notificationEvent($device, $this->pushNotification($request))) ? [$event] : [];
    }

    /** The decoded notification of an HTTP host push; throws when there is none. */
    public function pushNotification(Request $request): array
    {
        $payload = $this->pushPayload($request);
        if (! is_array($payload)) {
            throw new DeviceCommunicationException(DeviceCommunicationException::BAD_RESPONSE, 'Push request has no readable event payload.');
        }

        return $payload;
    }

    /**
     * The event notification of an HTTP host push: a form field or uploaded
     * part named AccessControllerEvent / event_log (JSON or XML), or a raw JSON
     * or XML body. Picture parts are ignored.
     */
    private function pushPayload(Request $request): ?array
    {
        foreach (['AccessControllerEvent', 'event_log', 'EventNotificationAlert'] as $field) {
            $value = $request->hasFile($field) ? $request->file($field)->get() : $request->input($field);
            if (is_array($value)) {
                return $value;
            }
            if (is_string($value) && $value !== '') {
                return $this->decodeNotification($value);
            }
        }

        return $this->decodeNotification((string) $request->getContent());
    }

    /** JSON or XML notification text -> array (XML root element ignored, as in alertStream). */
    private function decodeNotification(string $text): ?array
    {
        $text = trim($text);
        if (str_starts_with($text, '{')) {
            $data = json_decode($text, true);

            return is_array($data) ? $data : null;
        }

        return $this->xml($text)['root'] ?? null;
    }

    /**
     * One event notification (HTTP listening push or alertStream part) ->
     * NormalizedEvent, or null when it is not an attendance record this device
     * collects: other event types (heartbeats), other major/minor codes (door,
     * alarm, operation events) and records without a person are ignored, with
     * the same major/minor filter the AcsEvent search uses.
     */
    public function notificationEvent(AttendanceDevice $device, array $payload): ?NormalizedEvent
    {
        if (($payload['eventType'] ?? null) !== 'AccessControllerEvent') {
            return null;
        }

        $access = $payload['AccessControllerEvent'] ?? [];
        $major = (int) ($device->settings['acs_event_major'] ?? self::DEFAULT_MAJOR);
        if ((int) ($access['majorEventType'] ?? -1) !== $major || ! in_array((int) ($access['subEventType'] ?? -1), $this->minors($device), true)) {
            return null;
        }

        return $this->normalize($device, [
            'serialNo' => $access['serialNo'] ?? null,
            'time' => $payload['dateTime'] ?? '',
            'employeeNoString' => $access['employeeNoString'] ?? $access['employeeNo'] ?? null,
            'attendanceStatus' => $access['attendanceStatus'] ?? null,
            'currentVerifyMode' => $access['currentVerifyMode'] ?? null,
            'major' => $access['majorEventType'] ?? null,
            'minor' => $access['subEventType'] ?? null,
        ]);
    }

    /** Device record -> NormalizedEvent; null for records that are not a person's attendance. */
    private function normalize(AttendanceDevice $device, array $record): ?NormalizedEvent
    {
        $user = $record['employeeNoString'] ?? $record['employeeNo'] ?? null;
        if ($user === null || $user === '') {
            return null; // door/alarm records without a person
        }

        $raw = (string) ($record['time'] ?? '');
        $occurredAt = EventTime::toUtc($raw, $device->timezone);

        return new NormalizedEvent(
            deviceUserId: (string) $user,
            deviceEventId: isset($record['serialNo']) ? (string) $record['serialNo'] : null,
            eventTimeRaw: $raw,
            occurredAt: $occurredAt,
            eventType: self::STATUS_MAP[$record['attendanceStatus'] ?? ''] ?? 'unknown',
            verifyMode: isset($record['currentVerifyMode']) ? (string) $record['currentVerifyMode'] : null,
            payload: array_filter([
                'major' => $record['major'] ?? null,
                'minor' => $record['minor'] ?? null,
                'serialNo' => $record['serialNo'] ?? null,
                'attendanceStatus' => $record['attendanceStatus'] ?? null,
                'currentVerifyMode' => $record['currentVerifyMode'] ?? null,
            ], fn ($v) => $v !== null),
            invalidReason: $occurredAt ? null : 'Unparseable or implausible device timestamp.',
        );
    }

    private function clockDrift(AttendanceDevice $device): ?int
    {
        try {
            $time = $this->json($this->request($device)->get($device->baseUrl().'/ISAPI/System/time', ['format' => 'json']), 'Time');
            $local = EventTime::toUtc((string) ($time['localTime'] ?? ''), $device->timezone);

            return $local ? (int) CarbonImmutable::now()->diffInSeconds($local, false) : null;
        } catch (Throwable) {
            return null; // clock check is optional; not every firmware exposes it
        }
    }

    /** @return array<int, int> */
    private function minors(AttendanceDevice $device): array
    {
        $minors = $device->settings['acs_event_minors'] ?? self::DEFAULT_MINORS;

        return array_values(array_map('intval', (array) $minors)) ?: self::DEFAULT_MINORS;
    }

    private function request(AttendanceDevice $device): PendingRequest
    {
        if (! $device->host) {
            throw new DeviceCommunicationException(DeviceCommunicationException::CONNECTION_FAILED, 'The device has no host configured.');
        }

        return Http::withDigestAuth((string) $device->username, (string) $device->password)
            ->acceptJson()
            ->timeout((int) config('attendance.sync.http_timeout_seconds', 15))
            ->withOptions(['verify' => (bool) $device->verify_tls]);
    }

    /**
     * Decode a JSON response (or XML, which some firmware returns even when
     * ?format=json is requested) and return its $root section, or throw a
     * credential-free error naming what was received instead.
     */
    private function json(Response $response, string $root): array
    {
        if ($response->status() === 401) {
            throw new DeviceCommunicationException(DeviceCommunicationException::AUTH_FAILED, 'The device rejected the configured credentials (HTTP 401).');
        }

        $data = $response->json();
        $xml = is_array($data) ? null : $this->xml($response->body());

        if ($response->failed()) {
            $body = $data ?? $xml['root'] ?? [];
            $detail = trim(($body['statusString'] ?? '').' '.($body['subStatusCode'] ?? ''));

            throw new DeviceCommunicationException(DeviceCommunicationException::DEVICE_ERROR, "The device returned HTTP {$response->status()}".($detail ? " ({$detail})" : '').'.');
        }

        if (is_array($data) && array_key_exists($root, $data)) {
            return (array) $data[$root];
        }

        if ($xml !== null && $xml['name'] === $root) {
            return $xml['root'];
        }

        $received = is_array($data)
            ? 'JSON keys: '.(implode(', ', array_slice(array_keys($data), 0, 5)) ?: 'none')
            : ($xml !== null ? "XML root: {$xml['name']}" : 'content type: '.($response->header('Content-Type') ?: 'unknown'));

        throw new DeviceCommunicationException(DeviceCommunicationException::BAD_RESPONSE, "The device response has no {$root} section ({$received}).");
    }

    /** @return array{name: string, root: array<string, mixed>}|null XML document as an array (namespaces ignored) */
    private function xml(string $body): ?array
    {
        if (! str_starts_with(ltrim($body), '<')) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($document === false) {
            return null;
        }

        return ['name' => $document->getName(), 'root' => json_decode((string) json_encode($document), true) ?: []];
    }

    private function assertPull(AttendanceDevice $device, string $operation): void
    {
        if ($device->connection_method !== 'isapi_pull') {
            throw UnsupportedDeviceOperation::for('hikvision', $device->connection_method, $operation);
        }
    }
}

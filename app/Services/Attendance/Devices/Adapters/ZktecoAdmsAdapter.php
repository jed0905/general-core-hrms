<?php

namespace App\Services\Attendance\Devices\Adapters;

use App\Models\AttendanceDevice;
use App\Services\Attendance\Devices\Contracts\DeviceAdapter;
use App\Services\Attendance\Devices\Support\ConnectionResult;
use App\Services\Attendance\Devices\Support\EventTime;
use App\Services\Attendance\Devices\Support\FetchResult;
use App\Services\Attendance\Devices\Support\NormalizedEvent;
use App\Services\Attendance\Devices\Support\UnsupportedDeviceOperation;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * ZKTeco devices over the HTTP "PUSH" protocol (ADMS / iclock): the device
 * connects to the HRMS, identifies itself by serial number (SN) and uploads
 * attendance records (table=ATTLOG) as tab-separated lines:
 *
 *   PIN \t YYYY-MM-DD HH:MM:SS \t status \t verify \t workcode ...
 *
 * Only ATTLOG is consumed; the handshake asks for attendance logs only, never
 * fingerprint/face templates or photos. The HRMS cannot poll a PUSH device, so
 * connection test and pull retrieval are unsupported. The TCP (port 4370) /
 * Windows SDK protocol is NOT implemented: it is not publicly documented.
 * Protocol details follow ZKTeco's PUSH SDK overview and public server
 * implementations; they must be verified per model/firmware
 * (docs/development/attendance/ZKTECO_INTEGRATION.md).
 */
class ZktecoAdmsAdapter implements DeviceAdapter
{
    /** ATTLOG status column -> event type (PUSH protocol convention). */
    private const STATUS_MAP = [
        '0' => 'check_in',
        '1' => 'check_out',
        '2' => 'break_out',
        '3' => 'break_in',
        '4' => 'overtime_in',
        '5' => 'overtime_out',
    ];

    public function vendor(): string
    {
        return 'zkteco';
    }

    public function label(): string
    {
        return 'ZKTeco (PUSH / ADMS)';
    }

    public function connectionMethods(): array
    {
        return ['zk_adms' => 'Device pushes events to the HRMS (PUSH/ADMS)'];
    }

    public function capabilities(AttendanceDevice $device): array
    {
        return $device->connection_method === 'zk_adms' ? [self::CAP_PUSH] : [];
    }

    public function testConnection(AttendanceDevice $device): ConnectionResult
    {
        throw UnsupportedDeviceOperation::for('zkteco', $device->connection_method, 'Connection test (the device connects to the HRMS; check its last contact time)');
    }

    public function fetchEvents(AttendanceDevice $device, CarbonImmutable $from, CarbonImmutable $to, ?callable $onPage = null): FetchResult
    {
        throw UnsupportedDeviceOperation::for('zkteco', $device->connection_method, 'Event retrieval (PUSH devices upload their own logs)');
    }

    public function parsePush(AttendanceDevice $device, Request $request): array
    {
        if (strtoupper((string) $request->query('table')) !== 'ATTLOG') {
            return [];
        }

        $events = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($request->getContent())) as $line) {
            if (trim($line) === '') {
                continue;
            }

            $fields = explode("\t", $line);
            $pin = trim($fields[0] ?? '');
            $raw = trim($fields[1] ?? '');
            $status = trim($fields[2] ?? '');
            $occurredAt = EventTime::toUtc($raw, $device->timezone);

            if ($pin === '') {
                continue;
            }

            $events[] = new NormalizedEvent(
                deviceUserId: $pin,
                deviceEventId: null, // ATTLOG has no event serial; ingestion dedupes on user + time + type
                eventTimeRaw: $raw,
                occurredAt: $occurredAt,
                eventType: self::STATUS_MAP[$status] ?? 'unknown',
                verifyMode: ($fields[3] ?? '') !== '' ? trim($fields[3]) : null,
                payload: ['status' => $status, 'verify' => trim($fields[3] ?? ''), 'workcode' => trim($fields[4] ?? '')],
                invalidReason: $occurredAt ? null : 'Unparseable or implausible device timestamp.',
            );
        }

        return $events;
    }

    /**
     * Handshake reply (GET /iclock/cdata?SN=...&options=all): upload attendance
     * logs only, in real time, with the device's timezone offset.
     */
    public function handshake(AttendanceDevice $device): string
    {
        $offsetHours = CarbonImmutable::now($device->timezone)->utcOffset() / 60;
        $stamp = $device->sync_cursor['attlog_stamp'] ?? 'None';

        return implode("\n", [
            "GET OPTION FROM: {$device->serial_number}",
            "ATTLOGStamp={$stamp}",
            'OPERLOGStamp=9999',
            'ATTPHOTOStamp=None',
            'ErrorDelay=30',
            'Delay=10',
            'TransTimes=00:00;14:05',
            'TransInterval=1',
            'TransFlag=TransData AttLog',
            'TimeZone='.rtrim(rtrim(number_format($offsetHours, 2, '.', ''), '0'), '.'),
            'Realtime=1',
            'Encrypt=None',
        ]);
    }
}

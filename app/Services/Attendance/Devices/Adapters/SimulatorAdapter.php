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
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * A device that exists only in software, for development, demos and tests.
 * Its events are marked source "simulator" and never presented as real.
 * It goes through the same ingestion pipeline as real adapters.
 *
 * Scenario (attendance_devices.simulation):
 *   online (bool), failure (connection_failed|auth_failed|timeout|bad_response),
 *   fail_times (fail the first N requests, then succeed: retry behavior),
 *   model, firmware, serial, clock_drift_seconds,
 *   events: [{user, time, type?, id?, verify?, available_at?}]  explicit events;
 *           time may be naive device-local time, ISO 8601 with offset, or junk
 *           (invalid timestamp); available_at hides an event until that
 *           instant (a delayed upload);
 *   generate: {from, to, users[], in, out, jitter_minutes, seed, skip_weekends,
 *              duplicate_every, missing_every, reverse}  generated punches
 *              (duplicates, missing punches, out-of-order delivery).
 */
class SimulatorAdapter implements DeviceAdapter
{
    public function vendor(): string
    {
        return 'simulator';
    }

    public function label(): string
    {
        return 'Simulator (no hardware)';
    }

    public function connectionMethods(): array
    {
        return ['simulator' => 'Simulated pull'];
    }

    public function capabilities(AttendanceDevice $device): array
    {
        return [self::CAP_TEST_CONNECTION, self::CAP_DEVICE_INFO, self::CAP_FETCH_EVENTS, self::CAP_INCREMENTAL, self::CAP_CLOCK_CHECK];
    }

    public function testConnection(AttendanceDevice $device): ConnectionResult
    {
        $scenario = $this->scenario($device);
        $this->failIfConfigured($device, $scenario);

        return new ConnectionResult(
            ok: true,
            model: $scenario['model'] ?? 'SIMULATOR',
            serialNumber: $scenario['serial'] ?? $device->serial_number,
            firmware: $scenario['firmware'] ?? 'simulator',
            clockDriftSeconds: (int) ($scenario['clock_drift_seconds'] ?? 0),
        );
    }

    public function fetchEvents(AttendanceDevice $device, CarbonImmutable $from, CarbonImmutable $to, ?callable $onPage = null): FetchResult
    {
        $scenario = $this->scenario($device);
        $this->failIfConfigured($device, $scenario);

        $now = CarbonImmutable::now();
        $events = [];

        foreach ([...($scenario['events'] ?? []), ...$this->generated($scenario)] as $index => $raw) {
            if (isset($raw['available_at']) && CarbonImmutable::parse($raw['available_at'])->greaterThan($now)) {
                continue; // not uploaded yet (delayed event)
            }

            $occurredAt = EventTime::toUtc((string) $raw['time'], $device->timezone);
            if ($occurredAt && ($occurredAt->lessThan($from) || $occurredAt->greaterThan($to))) {
                continue;
            }

            $events[] = new NormalizedEvent(
                deviceUserId: (string) $raw['user'],
                deviceEventId: isset($raw['id']) ? (string) $raw['id'] : null,
                eventTimeRaw: (string) $raw['time'],
                occurredAt: $occurredAt,
                eventType: $raw['type'] ?? 'unknown',
                verifyMode: $raw['verify'] ?? 'simulated',
                payload: ['simulated' => true, 'index' => $index],
                invalidReason: $occurredAt ? null : 'Unparseable or implausible device timestamp.',
            );
        }

        if (! empty($scenario['generate']['reverse'])) {
            $events = array_reverse($events); // out-of-order delivery
        }

        if ($onPage) {
            $onPage($events); // the simulator delivers its window as one page

            return new FetchResult([], true, 1);
        }

        return new FetchResult($events);
    }

    public function parsePush(AttendanceDevice $device, Request $request): array
    {
        throw UnsupportedDeviceOperation::for('simulator', $device->connection_method, 'Device push');
    }

    /** @return array<int, array<string, mixed>> */
    private function generated(array $scenario): array
    {
        $plan = $scenario['generate'] ?? null;
        if (! $plan) {
            return [];
        }

        mt_srand((int) ($plan['seed'] ?? 1));
        $jitter = (int) ($plan['jitter_minutes'] ?? 0);
        $events = [];
        $counter = 0;

        foreach (CarbonPeriod::create($plan['from'], $plan['to']) as $date) {
            if (($plan['skip_weekends'] ?? true) && $date->isWeekend()) {
                continue;
            }
            foreach ($plan['users'] ?? [] as $user) {
                foreach ([['check_in', $plan['in'] ?? '08:00'], ['check_out', $plan['out'] ?? '17:00']] as [$type, $time]) {
                    $counter++;
                    if (! empty($plan['missing_every']) && $counter % (int) $plan['missing_every'] === 0) {
                        continue; // missing punch
                    }
                    $at = CarbonImmutable::parse($date->format('Y-m-d').' '.$time)->addMinutes($jitter ? mt_rand(-$jitter, $jitter) : 0);
                    $event = ['user' => $user, 'time' => $at->format('Y-m-d H:i:s'), 'type' => $type, 'id' => "gen-{$counter}"];
                    $events[] = $event;
                    if (! empty($plan['duplicate_every']) && $counter % (int) $plan['duplicate_every'] === 0) {
                        $events[] = $event; // same event delivered twice
                    }
                }
            }
        }

        return $events;
    }

    private function scenario(AttendanceDevice $device): array
    {
        return $device->simulation ?? [];
    }

    private function failIfConfigured(AttendanceDevice $device, array $scenario): void
    {
        if (($scenario['online'] ?? true) === false) {
            throw new DeviceCommunicationException(DeviceCommunicationException::CONNECTION_FAILED, 'Simulated device is offline.');
        }

        $failTimes = (int) ($scenario['fail_times'] ?? 0);
        if ($failTimes > 0) {
            $key = "attendance-simulator-failures:{$device->id}";
            $failed = (int) Cache::get($key, 0);
            if ($failed < $failTimes) {
                Cache::put($key, $failed + 1, 600);

                throw new DeviceCommunicationException($scenario['failure'] ?? DeviceCommunicationException::TIMEOUT, 'Simulated transient failure.');
            }
        } elseif (! empty($scenario['failure'])) {
            throw new DeviceCommunicationException($scenario['failure'], 'Simulated '.str_replace('_', ' ', $scenario['failure']).'.');
        }
    }
}

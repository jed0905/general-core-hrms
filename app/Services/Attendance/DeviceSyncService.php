<?php

namespace App\Services\Attendance;

use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceSyncRun;
use App\Models\User;
use App\Services\Attendance\Devices\Contracts\DeviceAdapter;
use App\Services\Attendance\Devices\DeviceAdapterRegistry;
use App\Services\Attendance\Devices\Support\DeviceCommunicationException;
use App\Services\Attendance\Devices\Support\NormalizedEvent;
use App\Services\Attendance\Devices\Support\UnsupportedDeviceOperation;
use App\Services\Attendance\Engine\AttendanceRecalculator;
use App\Services\Attendance\Ingestion\EventIngestionService;
use App\Services\Attendance\Ingestion\IngestResult;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Talks to devices through their adapters and records every attempt:
 * connection tests, pull synchronization (with retries for transient errors,
 * one sync per device at a time, an overlap window so late-stored events are
 * re-read) and push batches. Device status, last contact and the sync cursor
 * are updated here. Errors stored or shown never contain credentials.
 */
class DeviceSyncService
{
    public function __construct(
        protected DeviceAdapterRegistry $adapters,
        protected EventIngestionService $ingestion,
        protected AttendanceAuditLogger $audit,
    ) {}

    public function testConnection(AttendanceDevice $device, ?User $user): AttendanceDeviceSyncRun
    {
        $adapter = $this->adapters->for($device);
        $run = $this->startRun($device, 'test', $user);

        try {
            $result = $this->attempt(fn () => $adapter->testConnection($device), $run);
        } catch (UnsupportedDeviceOperation $e) {
            return $this->fail($device, $run, 'unsupported', $e->getMessage(), false);
        } catch (DeviceCommunicationException $e) {
            return $this->fail($device, $run, $e->errorCode, $e->getMessage());
        }

        $device->forceFill([
            'status' => AttendanceDevice::STATUS_ONLINE,
            'last_seen_at' => now(),
            'last_error' => null,
            'model' => $device->model ?: $result->model,
            'serial_number' => $device->serial_number ?: $result->serialNumber,
            'firmware_version' => $result->firmware ?? $device->firmware_version,
            'clock_drift_seconds' => $result->clockDriftSeconds,
            'capabilities' => $adapter->capabilities($device),
        ])->save();

        $message = $result->serialNumber && $device->serial_number && $result->serialNumber !== $device->serial_number
            ? "Connected, but the device reports serial {$result->serialNumber}, not the registered {$device->serial_number}."
            : null;
        $run->forceFill(['status' => $message ? 'partial' : 'succeeded', 'finished_at' => now(), 'error_message' => $message])->save();
        $this->audit->record($device, 'connection_tested', $user, ['ok' => true, 'model' => $result->model, 'firmware' => $result->firmware]);

        return $run;
    }

    /**
     * Pull events for [from, to]. Default window: from the end of the last
     * completed pull minus the overlap (plus any measured clock drift), so each
     * run reads only a few minutes; a new device reads the initial look-back.
     * A completed window advances the cursor when it continues it (an older
     * explicit window never moves it back; a later, non-adjacent one never
     * skips the gap).
     */
    public function sync(AttendanceDevice $device, string $trigger, ?User $user = null, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): AttendanceDeviceSyncRun
    {
        $adapter = $this->adapters->for($device);
        if (! $device->is_active) {
            throw ValidationException::withMessages(['device' => 'Inactive devices are not synchronized.']);
        }
        if (! in_array(DeviceAdapter::CAP_FETCH_EVENTS, $adapter->capabilities($device), true)) {
            throw ValidationException::withMessages(['device' => 'This device does not support pulling events with its connection method; it must push them (or use the connector).']);
        }

        $lock = Cache::lock("attendance-device-sync:{$device->id}", (int) config('attendance.sync.lock_seconds', 600));
        if (! $lock->get()) {
            throw ValidationException::withMessages(['device' => 'A synchronization of this device is already running.']);
        }

        try {
            $to ??= CarbonImmutable::now();
            $from ??= $this->defaultFrom($device);
            $run = $this->startRun($device, $trigger, $user, ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String()]);

            // Each page is ingested as it arrives (memory stays flat for large windows); attendance days
            // are recalculated once at the end. A retry after a failure re-reads the window and the pages
            // already stored count as duplicates.
            $source = $device->vendor === 'simulator' ? 'simulator' : 'device_pull';
            $result = new IngestResult;
            try {
                $fetch = $this->attempt(fn () => $adapter->fetchEvents($device, $from, $to, function (array $events) use ($device, $source, $run, $result) {
                    $result->merge($this->ingestion->ingest($device, $events, $source, $run, recalculate: false));
                }), $run);
            } catch (DeviceCommunicationException $e) {
                if ($result->touched !== []) {
                    app(AttendanceRecalculator::class)->recalculateTouched($result->touched); // pages stored before the failure
                }

                return $this->fail($device, $run, $e->errorCode, $e->getMessage());
            }

            if ($result->touched !== []) {
                app(AttendanceRecalculator::class)->recalculateTouched($result->touched);
            }

            $this->finish($device, $run, $result, $fetch->complete ? 'succeeded' : 'partial', $fetch->complete ? null : 'The device stopped paginating early; the next sync re-reads the window.');
            if ($fetch->complete) {
                $this->advanceCursor($device, $from, $to);
            }

            return $run->fresh();
        } finally {
            $lock->release();
        }
    }

    /**
     * Events pushed by a device (already authenticated by the controller).
     *
     * @param  array<int, NormalizedEvent>  $events
     */
    public function receivePush(AttendanceDevice $device, array $events, string $trigger = 'push', ?string $actorLabel = null): IngestResult
    {
        if ($trigger === 'push') {
            $this->markPushContact($device);
        }
        $run = $this->openPushRun($device, $trigger);
        $source = $trigger === 'connector' ? 'connector' : ($device->vendor === 'simulator' ? 'simulator' : 'device_push');
        $result = $this->ingestion->ingest($device, $events, $source, $run);

        $run->forceFill([
            'fetched' => $run->fetched + $result->fetched,
            'inserted' => $run->inserted + $result->inserted,
            'duplicates' => $run->duplicates + $result->duplicates,
            'unmatched' => $run->unmatched + $result->unmatched,
            'invalid' => $run->invalid + $result->invalid,
            'status' => 'succeeded',
            'finished_at' => now(),
        ])->save();

        $this->touchDevice($device, $result);

        return $result;
    }

    /** A device contacted the HRMS without events (heartbeat, handshake). */
    public function heartbeat(AttendanceDevice $device): void
    {
        if ($device->status !== AttendanceDevice::STATUS_ONLINE || ! $device->last_seen_at || $device->last_seen_at->lt(now()->subMinute())) {
            $device->forceFill(['status' => AttendanceDevice::STATUS_ONLINE, 'last_seen_at' => now()])->save();
        }
        $this->markPushContact($device);
    }

    /**
     * The device delivered something by push (event or heartbeat): its push
     * channel is working. Written at most once a minute (heartbeats arrive
     * every 30 s; health is judged over push_healthy_seconds).
     */
    public function markPushContact(AttendanceDevice $device): void
    {
        $seen = $device->sync_cursor['push_seen_at'] ?? null;
        if ($seen && CarbonImmutable::parse($seen)->greaterThan(CarbonImmutable::now()->subMinute())) {
            return;
        }

        $cursor = $device->fresh()?->sync_cursor ?? [];
        $cursor['push_seen_at'] = now()->utc()->toIso8601String();
        $device->forceFill(['sync_cursor' => $cursor])->save();
    }

    /**
     * Why a scheduled pull of this device is due now, or null to skip it.
     * Devices whose push channel (HTTP Listening heartbeats every 30 s, or
     * pushed events) was heard from recently get only a periodic catch-up
     * pull (reconciliation); devices without working push are pulled every
     * run, continuing from their cursor.
     */
    public function pullReason(AttendanceDevice $device): ?string
    {
        $cursor = $device->sync_cursor ?? [];
        $pushSeen = isset($cursor['push_seen_at']) ? CarbonImmutable::parse($cursor['push_seen_at']) : null;
        $pushHealthy = $pushSeen && $pushSeen->greaterThan(CarbonImmutable::now()->subSeconds((int) config('attendance.sync.push_healthy_seconds', 120)));

        if (! $pushHealthy) {
            return $pushSeen ? 'push silent since '.$pushSeen->toIso8601String() : 'no push channel';
        }

        $pulledTo = isset($cursor['pulled_to']) ? CarbonImmutable::parse($cursor['pulled_to']) : null;
        $interval = (int) config('attendance.sync.push_reconcile_minutes', 60);

        return ! $pulledTo || $pulledTo->lessThanOrEqualTo(CarbonImmutable::now()->subMinutes($interval))
            ? 'reconciliation (push active)'
            : null;
    }

    protected function defaultFrom(AttendanceDevice $device): CarbonImmutable
    {
        $overlap = (int) config('attendance.sync.overlap_minutes', 10) * 60 + abs((int) $device->clock_drift_seconds);
        $anchor = $device->sync_cursor['pulled_to'] ?? $device->sync_cursor['last_event_at'] ?? null;

        return $anchor
            ? CarbonImmutable::parse($anchor)->subSeconds($overlap)
            : CarbonImmutable::now()->subDays((int) config('attendance.sync.initial_days', 7));
    }

    /** Record the end of a completed pull window when it continues the previous one. */
    protected function advanceCursor(AttendanceDevice $device, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $device->refresh();
        $cursor = $device->sync_cursor ?? [];
        $baseline = $cursor['pulled_to'] ?? $cursor['last_event_at'] ?? null;
        $baseline = $baseline ? CarbonImmutable::parse($baseline) : null;

        if ($baseline === null || ($from->lessThanOrEqualTo($baseline) && $to->greaterThan($baseline))) {
            $cursor['pulled_to'] = $to->utc()->toIso8601String();
            $device->forceFill(['sync_cursor' => $cursor])->save();
        }
    }

    /** Run $call, retrying transient failures with backoff; counts attempts on the run. */
    protected function attempt(callable $call, AttendanceDeviceSyncRun $run): mixed
    {
        $max = max(1, (int) config('attendance.sync.max_attempts', 3));

        for ($attempt = 1; ; $attempt++) {
            try {
                $run->forceFill(['attempts' => $attempt])->save();

                return $call();
            } catch (ConnectionException $e) {
                $error = DeviceCommunicationException::fromTransport($e);
            } catch (DeviceCommunicationException $e) {
                $error = $e;
            }

            if (! $error->isRetryable() || $attempt >= $max) {
                throw $error;
            }

            usleep((int) config('attendance.sync.retry_backoff_ms', 2000) * 1000 * $attempt);
        }
    }

    protected function startRun(AttendanceDevice $device, string $trigger, ?User $user, ?array $window = null): AttendanceDeviceSyncRun
    {
        return AttendanceDeviceSyncRun::create([
            'attendance_device_id' => $device->id,
            'trigger' => $trigger,
            'status' => 'running',
            'started_at' => now(),
            'window' => $window,
            'triggered_by' => $user?->id,
        ]);
    }

    protected function openPushRun(AttendanceDevice $device, string $trigger): AttendanceDeviceSyncRun
    {
        // One run row per device per hour for push traffic, so frequent pushes don't flood the history.
        return AttendanceDeviceSyncRun::where('attendance_device_id', $device->id)
            ->where('trigger', $trigger)
            ->where('started_at', '>=', now()->startOfHour())
            ->latest('id')
            ->first()
            ?? $this->startRun($device, $trigger, null);
    }

    protected function finish(AttendanceDevice $device, AttendanceDeviceSyncRun $run, IngestResult $result, string $status, ?string $message): void
    {
        $run->forceFill([
            'status' => $status,
            'finished_at' => now(),
            'fetched' => $result->fetched,
            'inserted' => $result->inserted,
            'duplicates' => $result->duplicates,
            'unmatched' => $result->unmatched,
            'invalid' => $result->invalid,
            'error_message' => $message,
        ])->save();

        $this->touchDevice($device, $result, true);
    }

    protected function touchDevice(AttendanceDevice $device, IngestResult $result, bool $pulled = false): void
    {
        // Merge into the stored cursor, not this instance's copy: a long-running listener holds an old
        // instance, and writing its cursor back would move the polling cursor backwards.
        $cursor = $device->fresh()?->sync_cursor ?? [];
        if ($result->latestOccurredAt && ($cursor['last_event_at'] ?? '') < $result->latestOccurredAt) {
            $cursor['last_event_at'] = $result->latestOccurredAt;
        }

        $device->forceFill(array_filter([
            'status' => AttendanceDevice::STATUS_ONLINE,
            'last_seen_at' => now(),
            'last_sync_at' => now(),
            'last_success_at' => now(),
            'last_error' => $pulled ? null : $device->last_error,
            'sync_cursor' => $cursor,
        ], fn ($v) => $v !== null || $pulled))->save();
    }

    protected function fail(AttendanceDevice $device, AttendanceDeviceSyncRun $run, string $code, string $message, bool $affectsStatus = true): AttendanceDeviceSyncRun
    {
        $message = mb_substr($message, 0, 500);
        $run->forceFill(['status' => 'failed', 'finished_at' => now(), 'error_code' => $code, 'error_message' => $message])->save();

        if ($affectsStatus) {
            $offline = in_array($code, DeviceCommunicationException::RETRYABLE, true);
            $device->forceFill([
                'status' => $offline ? AttendanceDevice::STATUS_OFFLINE : AttendanceDevice::STATUS_ERROR,
                'last_sync_at' => $run->trigger === 'test' ? $device->last_sync_at : now(),
                'last_error' => $message,
            ])->save();
        }

        return $run;
    }

    /** Safe description of an unexpected error (no exception message, which may carry URLs). */
    public static function unexpected(Throwable $e): string
    {
        return 'Unexpected error ('.class_basename($e).'); see the server log.';
    }
}

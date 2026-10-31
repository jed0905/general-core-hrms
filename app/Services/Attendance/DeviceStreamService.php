<?php

namespace App\Services\Attendance;

use App\Models\AttendanceDevice;
use App\Services\Attendance\Devices\Adapters\HikvisionIsapiAdapter;
use App\Services\Attendance\Devices\DeviceAdapterRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Near-real-time ingestion of device notifications: the Hikvision alertStream
 * and HTTP host notification (push). Each notification is classified by the adapter with the same
 * rules as polling and handed to DeviceSyncService::receivePush, so mapping,
 * unmatched handling and the dedupe key (device + serialNo + instant) are the
 * ones polling uses: an event delivered by both is stored once.
 *
 * On connect the device replays stored records it has not delivered yet
 * (currentEvent = false), and a newly configured HTTP host receives the
 * device's stored log. Records older than the polling cursor minus the overlap
 * are skipped on both paths, so neither imports old history; polling remains
 * the recovery path for anything missed.
 */
class DeviceStreamService
{
    public const OUTCOMES = ['stored', 'duplicate', 'unmapped', 'ignored', 'replay_skipped'];

    public function __construct(
        protected DeviceAdapterRegistry $adapters,
        protected DeviceSyncService $sync,
    ) {}

    public function supports(AttendanceDevice $device): bool
    {
        return $device->is_active && $device->vendor === 'hikvision' && $device->connection_method === 'isapi_pull' && $device->host;
    }

    /**
     * @param  string  $trigger  'stream' (alertStream) or 'push' (HTTP host notification)
     * @return string one of OUTCOMES
     */
    public function handle(AttendanceDevice $device, array $notification, string $trigger = 'stream'): string
    {
        $adapter = $this->adapters->for($device);
        $event = $adapter instanceof HikvisionIsapiAdapter ? $adapter->notificationEvent($device, $notification) : null;
        if ($event === null) {
            return 'ignored';
        }

        // A push is meant to be live, so any pushed event behind the polling cursor is history (the
        // device drains its log to a newly configured host); on the stream only replays are checked.
        $replayed = $trigger === 'push' || ($notification['AccessControllerEvent']['currentEvent'] ?? true) === false;
        if ($replayed && ($event->occurredAt === null || $event->occurredAt->lessThan($this->replayFloor($device)))) {
            return 'replay_skipped';
        }

        $result = $this->sync->receivePush($device, [$event], $trigger);

        return match (true) {
            $result->inserted > 0 && $result->unmatched > 0 => 'unmapped',
            $result->inserted > 0 => 'stored',
            default => 'duplicate',
        };
    }

    /** Replayed records before this instant are history that polling already covered. */
    public function replayFloor(AttendanceDevice $device): CarbonImmutable
    {
        $anchor = $device->sync_cursor['pulled_to'] ?? $device->sync_cursor['last_event_at'] ?? null;
        $overlap = (int) config('attendance.sync.overlap_minutes', 10);

        return $anchor ? CarbonImmutable::parse($anchor)->subMinutes($overlap) : CarbonImmutable::now()->subMinutes($overlap);
    }

    /** Listener state for `attendance:listen --status` (file cache, shared across processes). */
    public function recordState(AttendanceDevice $device, array $state): void
    {
        Cache::put("attendance-stream:{$device->id}", [...(Cache::get("attendance-stream:{$device->id}") ?? []), ...$state, 'updated_at' => now()->toIso8601String()], now()->addDay());
    }

    public function state(AttendanceDevice $device): ?array
    {
        return Cache::get("attendance-stream:{$device->id}");
    }
}

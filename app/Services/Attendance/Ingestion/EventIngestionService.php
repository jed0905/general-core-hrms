<?php

namespace App\Services\Attendance\Ingestion;

use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceSyncRun;
use App\Models\AttendanceEvent;
use App\Models\BiometricEnrollment;
use App\Models\User;
use App\Services\Attendance\Devices\Support\NormalizedEvent;
use App\Services\Attendance\Engine\AttendanceRecalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

/**
 * The single entry point for raw attendance events from every source (device
 * pull and push, connector, simulator, manual entry, CSV).
 *
 * - Idempotent: a deterministic dedupe key (device + device event ID + instant,
 *   or device/employee + user + instant + type when the device has no event ID)
 *   is unique in the database, so repeated, delayed or out-of-order deliveries
 *   are stored once.
 * - The device's original time string and timezone are kept; occurred_at is UTC.
 * - Events are mapped to employees through the enrollment active at the event
 *   time; unknown device users are stored as "unmatched" (mappable later),
 *   unparseable timestamps as "invalid". Nothing is discarded.
 * - Affected work dates are recalculated afterwards (separately from ingestion).
 */
class EventIngestionService
{
    /** @param  array<int, NormalizedEvent>  $events */
    public function ingest(
        ?AttendanceDevice $device,
        array $events,
        string $source,
        ?AttendanceDeviceSyncRun $run = null,
        ?User $creator = null,
        ?int $employeeId = null,
        ?string $timezone = null,
        ?string $remarks = null,
        bool $recalculate = true,
    ): IngestResult {
        $result = new IngestResult(fetched: count($events));
        $now = CarbonImmutable::now();
        $timezone = $device?->timezone ?? $timezone ?? config('app.timezone');

        // One query for the whole batch finds events already stored (a pull re-reading its overlap
        // window is mostly duplicates); keys seen earlier in this batch count as duplicates too.
        $keys = array_map(fn (NormalizedEvent $event) => $this->dedupeKey($device, $employeeId, $event, $source), $events);
        $known = $keys === [] ? [] : array_fill_keys(
            AttendanceEvent::whereIn('dedupe_key', array_unique($keys))->pluck('dedupe_key')->all(),
            true,
        );

        foreach ($events as $index => $event) {
            $occurredAt = $event->occurredAt;
            $key = $keys[$index];

            if (isset($known[$key])) {
                $result->duplicates++;

                continue;
            }
            $known[$key] = true;

            [$status, $reason, $enrollment, $resolvedEmployee] = $this->classify($device, $event, $employeeId);
            $instant = $occurredAt ?? $now;

            try {
                AttendanceEvent::create([
                    'source' => $source,
                    'attendance_device_id' => $device?->id,
                    'biometric_enrollment_id' => $enrollment?->id,
                    'employee_id' => $resolvedEmployee,
                    'attendance_device_sync_run_id' => $run?->id,
                    'vendor' => $device?->vendor,
                    'device_model' => $device?->model,
                    'device_user_id' => $event->deviceUserId,
                    'device_event_id' => $event->deviceEventId,
                    'event_time_raw' => mb_substr($event->eventTimeRaw, 0, 64),
                    'device_timezone' => $timezone,
                    'occurred_at' => $instant,
                    'local_date' => $instant->setTimezone($timezone)->toDateString(),
                    'event_type' => in_array($event->eventType, AttendanceEvent::TYPES, true) ? $event->eventType : 'unknown',
                    'verify_mode' => $event->verifyMode !== null ? mb_substr($event->verifyMode, 0, 48) : null,
                    'payload' => $event->payload ?: null,
                    'received_at' => $now,
                    'dedupe_key' => $key,
                    'status' => $status,
                    'status_reason' => $reason,
                    'remarks' => $remarks,
                    'created_by' => $creator?->id,
                ]);
            } catch (QueryException $e) {
                if (! AttendanceEvent::where('dedupe_key', $key)->exists()) {
                    throw $e;
                }
                $result->duplicates++; // inserted concurrently by another delivery

                continue;
            }

            $result->inserted++;
            match ($status) {
                AttendanceEvent::STATUS_UNMATCHED => $result->unmatched++,
                AttendanceEvent::STATUS_INVALID => $result->invalid++,
                default => null,
            };

            if ($status === AttendanceEvent::STATUS_ACCEPTED) {
                $date = $instant->setTimezone($timezone)->toDateString();
                $result->touched[$resolvedEmployee][$date] = true;
                $result->latestOccurredAt = max($result->latestOccurredAt ?? '', $instant->toIso8601String());
            }
        }

        if ($recalculate && $result->touched !== []) {
            app(AttendanceRecalculator::class)->recalculateTouched($result->touched);
        }

        return $result;
    }

    /**
     * Map previously unmatched events of a device user once an enrollment exists.
     *
     * @return int events mapped
     */
    public function remapUnmatched(BiometricEnrollment $enrollment): int
    {
        $touched = [];
        $events = AttendanceEvent::where('attendance_device_id', $enrollment->attendance_device_id)
            ->where('device_user_id', $enrollment->device_user_id)
            ->where('status', AttendanceEvent::STATUS_UNMATCHED)
            ->when($enrollment->enrolled_on, fn ($q) => $q->where('local_date', '>=', $enrollment->enrolled_on->toDateString()))
            ->get();

        foreach ($events as $event) {
            $event->update([
                'employee_id' => $enrollment->employee_id,
                'biometric_enrollment_id' => $enrollment->id,
                'status' => AttendanceEvent::STATUS_ACCEPTED,
                'status_reason' => 'Mapped after enrollment #'.$enrollment->id,
            ]);
            $touched[$enrollment->employee_id][$event->local_date->toDateString()] = true;
        }

        if ($touched) {
            app(AttendanceRecalculator::class)->recalculateTouched($touched);
        }

        return $events->count();
    }

    public function dedupeKey(?AttendanceDevice $device, ?int $employeeId, NormalizedEvent $event, string $source): string
    {
        $instant = $event->occurredAt?->toIso8601String() ?? 'invalid:'.$event->eventTimeRaw;
        $scope = $device ? "device:{$device->id}" : "employee:{$employeeId}:{$source}";

        $parts = $event->deviceEventId !== null
            ? [$scope, 'event', $event->deviceEventId, $instant]
            : [$scope, 'user', $event->deviceUserId ?? "employee:{$employeeId}", $instant, $event->eventType];

        return hash('sha256', implode('|', $parts));
    }

    /** @return array{0: string, 1: ?string, 2: ?BiometricEnrollment, 3: ?int} */
    private function classify(?AttendanceDevice $device, NormalizedEvent $event, ?int $employeeId): array
    {
        if ($event->occurredAt === null) {
            return [AttendanceEvent::STATUS_INVALID, $event->invalidReason ?? 'Invalid timestamp.', null, null];
        }

        if ($employeeId !== null) {
            return [AttendanceEvent::STATUS_ACCEPTED, null, null, $employeeId];
        }

        if (! $device || $event->deviceUserId === null) {
            return [AttendanceEvent::STATUS_UNMATCHED, 'No device user.', null, null];
        }

        $date = $event->occurredAt->setTimezone($device->timezone)->toDateString();
        $enrollment = BiometricEnrollment::where('attendance_device_id', $device->id)
            ->where('device_user_id', $event->deviceUserId)
            ->where('status', '<>', BiometricEnrollment::STATUS_PENDING)
            ->where(fn ($q) => $q->whereNull('enrolled_on')->orWhere('enrolled_on', '<=', $date))
            ->where(fn ($q) => $q->whereNull('deactivated_at')->orWhere('deactivated_at', '>=', $event->occurredAt))
            ->orderByDesc('id')
            ->first();

        return $enrollment
            ? [AttendanceEvent::STATUS_ACCEPTED, null, $enrollment, $enrollment->employee_id]
            : [AttendanceEvent::STATUS_UNMATCHED, 'Device user '.$event->deviceUserId.' is not mapped to an employee on this device.', null, null];
    }
}

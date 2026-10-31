<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceDevice;
use App\Models\AttendanceEvent;
use App\Services\Attendance\DeviceStreamService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

/**
 * alertStream notifications (shape as observed on a DS-K1T321MFX, V4.61.0)
 * go through the same classification and ingestion as polling: door events
 * are ignored, replayed history behind the polling cursor is skipped, and an
 * event delivered by both the stream and a poll is stored once.
 */
class DeviceStreamTest extends AttendanceTestCase
{
    private function hikvision(): AttendanceDevice
    {
        return AttendanceDevice::create([
            'name' => 'Lobby', 'vendor' => 'hikvision', 'serial_number' => 'FX1', 'timezone' => 'Asia/Manila', 'connection_method' => 'isapi_pull',
            'host' => '192.0.2.10', 'port' => 80, 'username' => 'admin', 'password' => 'S3cret-pass', 'page_size' => 30,
            'sync_cursor' => ['pulled_to' => '2026-10-14T03:00:00+00:00'],
        ]);
    }

    private function notification(int $serial, string $time, array $access = [], bool $current = true): array
    {
        return [
            'ipAddress' => '192.0.2.10', 'dateTime' => $time, 'eventType' => 'AccessControllerEvent', 'eventState' => 'active',
            'AccessControllerEvent' => array_merge([
                'majorEventType' => 5, 'subEventType' => 38, 'serialNo' => $serial, 'employeeNoString' => '220464', 'name' => 'ERIC',
                'attendanceStatus' => 'checkIn', 'label' => 'Check In', 'currentVerifyMode' => 'faceOrFpOrCardOrPw', 'cardNo' => '123', 'currentEvent' => $current,
            ], $access),
        ];
    }

    #[Test]
    public function a_live_attendance_notification_is_stored_like_a_polled_event(): void
    {
        $device = $this->hikvision();
        $employee = $this->scheduledEmployee();
        $this->enroll($device, $employee, '220464');

        $outcome = app(DeviceStreamService::class)->handle($device, $this->notification(612, '2026-10-14T11:58:00+08:00'));

        $event = AttendanceEvent::sole();
        $this->assertSame('stored', $outcome);
        $this->assertSame(['device_push', '612', '220464', 'check_in', '2026-10-14T11:58:00+08:00', $employee->id], [$event->source, $event->device_event_id, $event->device_user_id, $event->event_type, $event->event_time_raw, $event->employee_id]);
        $this->assertSame('2026-10-14T03:58:00+00:00', $event->occurred_at->toIso8601String());
        $this->assertArrayNotHasKey('name', $event->payload);
        $this->assertArrayNotHasKey('cardNo', $event->payload);
        $this->assertSame('stream', $device->syncRuns()->latest('id')->value('trigger'));
    }

    #[Test]
    public function door_events_heartbeats_and_unconfigured_codes_are_ignored(): void
    {
        $device = $this->hikvision();
        $streams = app(DeviceStreamService::class);

        $this->assertSame('ignored', $streams->handle($device, $this->notification(1, '2026-10-14T11:00:00+08:00', ['majorEventType' => 2, 'subEventType' => 1024, 'employeeNoString' => null])));
        $this->assertSame('ignored', $streams->handle($device, $this->notification(2, '2026-10-14T11:00:01+08:00', ['subEventType' => 75])));
        $this->assertSame('ignored', $streams->handle($device, ['eventType' => 'heartBeat', 'dateTime' => '2026-10-14T11:00:02+08:00']));
        $this->assertSame(0, AttendanceEvent::count());
    }

    #[Test]
    public function replayed_history_is_skipped_but_replayed_events_after_the_cursor_are_recovered(): void
    {
        $device = $this->hikvision(); // polled up to 11:00 Manila (03:00 UTC); overlap 10 min
        $streams = app(DeviceStreamService::class);

        $this->assertSame('replay_skipped', $streams->handle($device, $this->notification(5, '2025-03-17T19:46:44+08:00', current: false)));
        $this->assertSame('unmapped', $streams->handle($device, $this->notification(6, '2026-10-14T11:20:00+08:00', current: false)));
        $this->assertSame(['6'], AttendanceEvent::pluck('device_event_id')->all());
        $this->assertSame('unmatched', AttendanceEvent::sole()->status); // kept for a later mapping
    }

    #[Test]
    public function an_event_received_by_stream_and_by_polling_is_stored_once(): void
    {
        $device = $this->hikvision();
        app(DeviceStreamService::class)->handle($device, $this->notification(612, '2026-10-14T11:58:00+08:00'));
        Http::fake(['*/ISAPI/AccessControl/AcsEvent*' => Http::response(['AcsEvent' => ['responseStatusStrg' => 'OK', 'numOfMatches' => 1, 'InfoList' => [
            ['major' => 5, 'minor' => 38, 'time' => '2026-10-14T11:58:00+08:00', 'employeeNoString' => '220464', 'attendanceStatus' => 'checkIn', 'serialNo' => 612, 'currentVerifyMode' => 'faceOrFpOrCardOrPw'],
        ]]])]);

        $run = $this->sync($device->fresh(), '2026-10-14 03:30', '2026-10-14 04:30');

        $this->assertSame([1, 0, 1], [$run->fetched, $run->inserted, $run->duplicates]);
        $this->assertSame(1, AttendanceEvent::count());
        $this->assertSame('duplicate', app(DeviceStreamService::class)->handle($device->fresh(), $this->notification(612, '2026-10-14T11:58:00+08:00')));
    }

    #[Test]
    public function a_listener_holding_an_old_device_instance_never_moves_the_polling_cursor_back(): void
    {
        $listenerCopy = $this->hikvision(); // loaded when the stream connected (pulled_to 03:00 UTC)
        $this->enroll($listenerCopy, $this->scheduledEmployee(), '220464');
        $listenerCopy->newQuery()->whereKey($listenerCopy->id)->update(['sync_cursor' => json_encode(['pulled_to' => '2026-10-14T04:30:00+00:00'])]); // polling advanced since

        app(DeviceStreamService::class)->handle($listenerCopy, $this->notification(700, '2026-10-14T12:31:00+08:00'));

        $this->assertSame('2026-10-14T04:30:00+00:00', $listenerCopy->fresh()->sync_cursor['pulled_to']);
        $this->assertSame('2026-10-14T04:31:00+00:00', $listenerCopy->fresh()->sync_cursor['last_event_at']);
    }

    #[Test]
    public function the_listener_needs_exactly_one_streamable_device(): void
    {
        $this->artisan('attendance:listen')->expectsOutputToContain('No active Hikvision device')->assertFailed();
        $this->hikvision();
        $this->artisan('attendance:listen', ['--status' => true])->expectsOutputToContain('never started')->assertSuccessful();
    }
}

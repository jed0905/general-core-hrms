<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceDevice;
use App\Models\AttendanceEvent;
use App\Models\BiometricEnrollment;
use App\Services\Attendance\DeviceSyncService;
use App\Services\Attendance\Ingestion\EventIngestionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;

/**
 * Device synchronization and raw-event ingestion, using the simulator (no
 * hardware). Every source goes through the same idempotent pipeline; nothing is
 * discarded (unknown users are "unmatched", bad timestamps "invalid").
 */
class DeviceIngestionTest extends AttendanceTestCase
{
    #[Test]
    public function a_sync_ingests_events_once_however_often_it_runs(): void
    {
        $employee = $this->scheduledEmployee();
        $device = $this->device(['simulation' => ['events' => [
            ['user' => '1001', 'time' => '2026-10-12 07:59:00', 'type' => 'check_in', 'id' => '501'],
            ['user' => '1001', 'time' => '2026-10-12 17:03:00', 'type' => 'check_out', 'id' => '502'],
            ['user' => '1001', 'time' => '2026-10-12 17:03:00', 'type' => 'check_out', 'id' => '502'], // delivered twice
        ]]]);
        $this->enroll($device, $employee, '1001');

        $first = $this->sync($device, '2026-10-11', '2026-10-13');
        $second = $this->sync($device, '2026-10-11', '2026-10-13');

        $this->assertSame([3, 2, 1], [$first->fetched, $first->inserted, $first->duplicates]);
        $this->assertSame([3, 0, 3], [$second->fetched, $second->inserted, $second->duplicates]);
        $this->assertSame(2, AttendanceEvent::count());
        $this->assertSame('simulator', AttendanceEvent::first()->source); // never presented as a real device
        $this->assertSame('2026-10-12 07:59:00', AttendanceEvent::first()->event_time_raw);
        $this->assertSame('Asia/Manila', AttendanceEvent::first()->device_timezone);
        $this->assertSame('present', $this->day($employee, '2026-10-12')->status);
        $device->refresh();
        $this->assertSame('online', $device->status);
        $this->assertSame('2026-10-12T09:03:00+00:00', $device->sync_cursor['last_event_at']);
    }

    #[Test]
    public function the_sync_command_can_pull_an_explicit_window_instead_of_the_initial_look_back(): void
    {
        $device = $this->device(['simulation' => ['events' => [
            ['user' => '7', 'time' => '2026-10-13 08:00:00', 'type' => 'check_in', 'id' => 'old'],
            ['user' => '7', 'time' => '2026-10-14 08:05:00', 'type' => 'check_in', 'id' => 'new'],
        ]]]);

        $this->artisan('attendance:sync-devices', ['--from' => '2026-10-14 08:00'])->assertFailed(); // needs --device
        $this->artisan('attendance:sync-devices', ['--device' => $device->id, '--from' => '2026-10-14 08:00', '--to' => '2026-10-14 09:00'])->assertSuccessful();

        $this->assertSame(['new'], AttendanceEvent::pluck('device_event_id')->all());
        $this->assertSame('manual', $device->syncRuns()->latest('id')->value('trigger'));
    }

    #[Test]
    public function a_delayed_upload_is_picked_up_by_a_later_sync_and_order_does_not_matter(): void
    {
        $employee = $this->scheduledEmployee();
        $device = $this->device(['simulation' => ['generate' => ['from' => '2026-10-12', 'to' => '2026-10-13', 'users' => ['42'], 'in' => '08:00', 'out' => '17:00', 'reverse' => true], 'events' => [
            ['user' => '42', 'time' => '2026-10-13 12:30:00', 'type' => 'break_out', 'id' => 'late-1', 'available_at' => '2026-10-14 18:00:00'],
            ['user' => '42', 'time' => '2026-10-13 13:00:00', 'type' => 'break_in', 'id' => 'late-2', 'available_at' => '2026-10-14 18:00:00'],
        ]]]);
        $this->enroll($device, $employee, '42');

        $this->sync($device, '2026-10-11', '2026-10-14');
        $this->assertSame(4, AttendanceEvent::count());
        $this->assertSame(480, $this->day($employee, '2026-10-13')->worked_minutes);

        Carbon::setTestNow('2026-10-14 19:00:00');
        $run = app(DeviceSyncService::class)->sync($device->fresh(), 'scheduled'); // short default window: the late records are older
        $this->assertSame(0, $run->inserted);

        $this->artisan('attendance:sync-devices', ['--lookback' => 2880])->assertSuccessful(); // reconciliation pass (these records are ~29 h late)
        $run = $device->syncRuns()->latest('id')->first();

        $this->assertSame(2, $run->inserted);
        $this->assertSame(510, $this->day($employee, '2026-10-13')->worked_minutes); // break punches now replace the scheduled break
    }

    #[Test]
    public function scheduled_pulls_continue_from_the_last_completed_window_with_a_short_overlap(): void
    {
        $device = $this->device(['clock_drift_seconds' => -30, 'simulation' => ['events' => [
            ['user' => '900', 'time' => '2026-10-14 11:58:00', 'type' => 'check_in', 'id' => 'u1'], // unmapped user
        ]]]);

        $first = app(DeviceSyncService::class)->sync($device, 'scheduled');
        $this->assertSame('2026-10-07T04:00:00+00:00', CarbonImmutable::parse($first->window['from'])->utc()->toIso8601String()); // initial look-back (7 days)
        $this->assertSame('2026-10-14T04:00:00+00:00', $device->fresh()->sync_cursor['pulled_to']); // advances although the event is unmapped

        Carbon::setTestNow('2026-10-14 12:01:00');
        $second = app(DeviceSyncService::class)->sync($device->fresh(), 'scheduled');

        // 10 minutes overlap + 30 s measured drift before the previous end; nothing re-imported.
        $this->assertSame('2026-10-14T03:49:30+00:00', CarbonImmutable::parse($second->window['from'])->utc()->toIso8601String());
        $this->assertSame([1, 0, 1], [$second->fetched, $second->inserted, $second->duplicates]); // the overlap re-reads 11:58; stored once
        $this->assertSame('2026-10-14T04:01:00+00:00', $device->fresh()->sync_cursor['pulled_to']);

        // An older explicit window never moves the cursor back; a later, non-adjacent one never skips the gap.
        $this->sync($device->fresh(), '2026-10-12 00:00', '2026-10-12 01:00');
        $this->sync($device->fresh(), '2026-10-14 05:00', '2026-10-14 06:00');
        $this->assertSame('2026-10-14T04:01:00+00:00', $device->fresh()->sync_cursor['pulled_to']);
    }

    #[Test]
    public function a_failed_pull_does_not_advance_the_cursor_and_does_not_stop_other_devices(): void
    {
        $offline = $this->device(['simulation' => ['online' => false]]);
        $online = $this->device(['simulation' => ['events' => [['user' => '5', 'time' => '2026-10-14 11:00:00', 'type' => 'check_in', 'id' => 'ok']]]]);
        $offline->forceFill(['sync_cursor' => ['pulled_to' => '2026-10-14T03:00:00+00:00']])->save();

        $this->artisan('attendance:sync-devices')->assertSuccessful();

        $this->assertSame('2026-10-14T03:00:00+00:00', $offline->fresh()->sync_cursor['pulled_to']);
        $this->assertSame('offline', $offline->fresh()->status);
        $this->assertSame(['ok'], AttendanceEvent::where('attendance_device_id', $online->id)->pluck('device_event_id')->all());
    }

    #[Test]
    public function a_device_already_being_synchronized_is_skipped_not_pulled_twice(): void
    {
        $device = $this->device(['simulation' => ['events' => [['user' => '5', 'time' => '2026-10-14 11:00:00', 'type' => 'check_in', 'id' => 'x']]]]);
        $lock = Cache::lock("attendance-device-sync:{$device->id}", 600);
        $lock->get();

        $this->artisan('attendance:sync-devices')->expectsOutputToContain('already running')->assertSuccessful();
        $this->assertSame(0, AttendanceEvent::count());

        $lock->release();
        $this->artisan('attendance:sync-devices')->assertSuccessful();
        $this->assertSame(1, AttendanceEvent::count());
    }

    #[Test]
    public function unknown_users_are_kept_unmatched_and_mapped_once_enrolled(): void
    {
        $employee = $this->scheduledEmployee();
        $device = $this->device(['simulation' => ['events' => [
            ['user' => '777', 'time' => '2026-10-12 08:00:00', 'type' => 'check_in', 'id' => '1'],
            ['user' => '777', 'time' => '2026-10-12 17:00:00', 'type' => 'check_out', 'id' => '2'],
        ]]]);

        $run = $this->sync($device, '2026-10-11', '2026-10-13');
        $this->assertSame(2, $run->unmatched);
        $this->assertSame(2, AttendanceEvent::where('status', 'unmatched')->count());

        $enrollment = $this->enroll($device, $employee, '777');
        $mapped = app(EventIngestionService::class)->remapUnmatched($enrollment);

        $this->assertSame(2, $mapped);
        $this->assertSame('present', $this->day($employee, '2026-10-12')->status);
    }

    #[Test]
    public function invalid_device_timestamps_are_stored_as_invalid_not_dropped(): void
    {
        $device = $this->device(['simulation' => ['events' => [
            ['user' => '1', 'time' => '0000-00-00 00:00:00', 'type' => 'check_in', 'id' => 'bad-1'],
            ['user' => '1', 'time' => 'not a time', 'type' => 'check_in', 'id' => 'bad-2'],
        ]]]);

        $run = $this->sync($device, '2026-10-11', '2026-10-13');

        $this->assertSame(2, $run->invalid);
        $this->assertSame(['0000-00-00 00:00:00', 'not a time'], AttendanceEvent::where('status', 'invalid')->orderBy('id')->pluck('event_time_raw')->all());
    }

    #[Test]
    public function an_employee_can_have_different_ids_on_different_devices(): void
    {
        $alice = $this->scheduledEmployee();
        $bob = $this->scheduledEmployee();
        $gate = $this->device(['simulation' => ['events' => [['user' => '10', 'time' => '2026-10-12 08:00:00', 'type' => 'check_in', 'id' => 'a']]]]);
        $canteen = $this->device(['simulation' => ['events' => [
            ['user' => '99', 'time' => '2026-10-12 17:00:00', 'type' => 'check_out', 'id' => 'a'], // same event id, other device
            ['user' => '10', 'time' => '2026-10-12 08:30:00', 'type' => 'check_in', 'id' => 'b'],  // same user id, other person
        ]]]);
        $this->enroll($gate, $alice, '10');
        $this->enroll($canteen, $alice, '99');
        $this->enroll($canteen, $bob, '10');

        $this->sync($gate, '2026-10-11', '2026-10-13');
        $this->sync($canteen, '2026-10-11', '2026-10-13');

        $this->assertSame(480, $this->day($alice, '2026-10-12')->worked_minutes);
        $this->assertSame(1, AttendanceEvent::where('employee_id', $bob->id)->count());
    }

    #[Test]
    public function one_device_user_id_can_have_only_one_active_mapping(): void
    {
        $device = $this->device();
        $this->enroll($device, $this->scheduledEmployee(), '10');
        $this->enroll($device, $this->scheduledEmployee(), '11');
        BiometricEnrollment::first()->update(['status' => 'inactive']);
        $this->enroll($device, $this->scheduledEmployee(), '10'); // reassigning after deactivation is fine

        $this->expectException(QueryException::class);
        $this->enroll($device, $this->scheduledEmployee(), '11');
    }

    #[Test]
    public function an_offline_device_is_recorded_as_offline(): void
    {
        $device = $this->device(['simulation' => ['online' => false]]);

        $run = $this->sync($device, '2026-10-11', '2026-10-13');

        $this->assertSame(['failed', 'connection_failed'], [$run->status, $run->error_code]);
        $this->assertSame(3, $run->attempts);
        $this->assertSame('offline', $device->fresh()->status);
        $this->assertSame('Simulated device is offline.', $device->fresh()->last_error);
    }

    #[Test]
    public function transient_failures_are_retried(): void
    {
        $device = $this->device(['simulation' => ['fail_times' => 2, 'failure' => 'timeout', 'events' => [['user' => '1', 'time' => '2026-10-12 08:00:00', 'id' => 'x']]]]);

        $run = $this->sync($device, '2026-10-11', '2026-10-13');

        $this->assertSame(['succeeded', 3, 1], [$run->status, $run->attempts, $run->inserted]);
    }

    #[Test]
    public function authentication_failures_are_not_retried(): void
    {
        $device = $this->device(['simulation' => ['failure' => 'auth_failed']]);

        $run = $this->sync($device, '2026-10-11', '2026-10-13');

        $this->assertSame(['failed', 'auth_failed', 1], [$run->status, $run->error_code, $run->attempts]);
        $this->assertSame('error', $device->fresh()->status);
    }

    #[Test]
    public function inactive_devices_and_push_only_devices_cannot_be_pulled(): void
    {
        $inactive = $this->device(['is_active' => false]);
        $push = AttendanceDevice::create(['name' => 'ZK', 'vendor' => 'zkteco', 'serial_number' => 'ZK1', 'timezone' => 'Asia/Manila', 'connection_method' => 'zk_adms']);

        $this->assertThrows(fn () => $this->sync($inactive, '2026-10-11', '2026-10-13'), ValidationException::class);
        $this->assertThrows(fn () => $this->sync($push, '2026-10-11', '2026-10-13'), ValidationException::class);
    }

    #[Test]
    public function the_connection_test_reports_device_details(): void
    {
        $device = $this->device(['model' => null, 'simulation' => ['model' => 'SIM-9', 'firmware' => 'v1.2', 'clock_drift_seconds' => -42]]);

        $run = app(DeviceSyncService::class)->testConnection($device, $this->hr);

        $device->refresh();
        $this->assertSame('succeeded', $run->status);
        $this->assertSame(['SIM-9', 'v1.2', -42], [$device->model, $device->firmware_version, $device->clock_drift_seconds]);
    }

    #[Test]
    public function raw_events_cannot_be_edited_or_deleted(): void
    {
        $device = $this->device();
        $this->enroll($device, $this->scheduledEmployee(), '7');
        $this->punch($device, '7', ['2026-10-12 08:00' => 'check_in']);
        $event = AttendanceEvent::first();

        $this->assertThrows(fn () => $event->update(['event_time_raw' => '2026-10-12 07:00']), LogicException::class);
        $this->assertThrows(fn () => $event->fresh()->delete(), LogicException::class);
    }
}

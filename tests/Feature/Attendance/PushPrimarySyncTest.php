<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceSyncRun;
use App\Models\AttendanceEvent;
use App\Services\Attendance\DeviceSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * HTTP Listening (push) is the primary path; ISAPI polling is the recovery
 * path. A device whose push channel was heard from recently (heartbeat every
 * 30 s or a pushed event) is only reconciled periodically; a device without
 * working push is pulled every scheduled run. Pulls are ingested page by page
 * and never advance the cursor on failure.
 */
class PushPrimarySyncTest extends AttendanceTestCase
{
    private function hikvision(array $attributes = []): AttendanceDevice
    {
        return AttendanceDevice::create(array_merge([
            'name' => 'Terminal', 'vendor' => 'hikvision', 'serial_number' => 'HK-P'.AttendanceDevice::count(), 'timezone' => 'Asia/Manila',
            'connection_method' => 'isapi_pull', 'host' => '192.0.2.20', 'port' => 80, 'username' => 'u', 'password' => 'p', 'page_size' => 30,
        ], $attributes));
    }

    /** A fake ISAPI device with $total code-38 events, served in pages of 30; $onRequest sees each search position. */
    private function fakeDevice(int $total, ?callable $onRequest = null): void
    {
        Http::fake(function (Request $request) use ($total, $onRequest) {
            $position = (int) ($request->data()['AcsEventCond']['searchResultPosition'] ?? 0);
            if ($onRequest && ($response = $onRequest($position)) !== null) {
                return $response;
            }
            $list = [];
            for ($i = $position; $i < min($total, $position + 30); $i++) {
                $list[] = ['major' => 5, 'minor' => 38, 'serialNo' => $i + 1, 'employeeNoString' => '7',
                    'time' => CarbonImmutable::parse('2026-10-14 08:00', 'Asia/Manila')->addSeconds($i)->toIso8601String(), 'attendanceStatus' => 'checkIn'];
            }

            return Http::response(['AcsEvent' => ['responseStatusStrg' => $position + 30 < $total ? 'MORE' : 'OK', 'numOfMatches' => count($list), 'InfoList' => $list]]);
        });
    }

    #[Test]
    public function a_healthy_push_device_is_only_reconciled_periodically(): void
    {
        $device = $this->hikvision(['sync_cursor' => ['pulled_to' => '2026-10-14T03:30:00+00:00']]); // 11:30 Manila; now 12:00
        $sync = app(DeviceSyncService::class);
        $sync->heartbeat($device);
        $this->fakeDevice(0);

        $this->assertNotNull($device->fresh()->sync_cursor['push_seen_at']);
        $this->assertNull($sync->pullReason($device->fresh()));
        $this->artisan('attendance:sync-devices')->assertSuccessful();
        Http::assertNothingSent();

        Carbon::setTestNow('2026-10-14 12:31:00'); // last pull 61 minutes ago; push still heard
        $sync->heartbeat($device->fresh());
        $this->assertSame('reconciliation (push active)', $sync->pullReason($device->fresh()));
        $this->artisan('attendance:sync-devices')->assertSuccessful();
        Http::assertSentCount(1);
    }

    #[Test]
    public function devices_without_working_push_are_pulled_every_run_and_force_overrides(): void
    {
        $silent = $this->hikvision(['sync_cursor' => ['pulled_to' => '2026-10-14T03:59:00+00:00', 'push_seen_at' => '2026-10-14T03:55:00+00:00']]); // heard 5 min ago
        $pullOnly = $this->hikvision(['sync_cursor' => ['pulled_to' => '2026-10-14T03:59:00+00:00']]);
        $healthy = $this->hikvision(['sync_cursor' => ['pulled_to' => '2026-10-14T03:59:00+00:00', 'push_seen_at' => '2026-10-14T03:59:30+00:00']]);
        $sync = app(DeviceSyncService::class);
        $this->fakeDevice(0);

        $this->assertStringStartsWith('push silent since', $sync->pullReason($silent));
        $this->assertSame('no push channel', $sync->pullReason($pullOnly));
        $this->assertNull($sync->pullReason($healthy));

        $this->artisan('attendance:sync-devices')->assertSuccessful();
        Http::assertSentCount(2); // silent + pull-only

        $this->artisan('attendance:sync-devices', ['--force' => true])->assertSuccessful();
        Http::assertSentCount(5); // all three
    }

    #[Test]
    public function a_pull_is_ingested_page_by_page_and_a_failure_keeps_the_cursor(): void
    {
        $device = $this->hikvision(['sync_cursor' => ['pulled_to' => '2026-10-14T03:00:00+00:00']]);
        $employee = $this->scheduledEmployee();
        $this->enroll($device, $employee, '7');
        $storedBeforePage = [];
        $failPage = true;
        $this->fakeDevice(90, function (int $position) use (&$storedBeforePage, &$failPage) {
            $storedBeforePage[$position] = AttendanceEvent::count();
            if ($position === 60 && $failPage) {
                return Http::response(['statusCode' => 6, 'statusString' => 'Invalid Content'], 400);
            }

            return null;
        });

        $run = app(DeviceSyncService::class)->sync($device, 'scheduled');

        $this->assertSame('failed', $run->status);
        $this->assertSame([0 => 0, 30 => 30, 60 => 60], $storedBeforePage); // each page stored before the next is requested
        $this->assertSame(60, AttendanceEvent::count());
        $this->assertSame('2026-10-14T03:00:00+00:00', $device->fresh()->sync_cursor['pulled_to']); // failure: cursor kept

        $failPage = false;
        $run = app(DeviceSyncService::class)->sync($device->fresh(), 'scheduled');

        $this->assertSame(['succeeded', 30, 60], [$run->status, $run->inserted, $run->duplicates]);
        $this->assertSame(90, AttendanceEvent::count()); // recovered, no duplicates
        $this->assertNotNull($this->day($employee, '2026-10-14')->computed_at); // days recalculated after the pull
    }

    #[Test]
    public function http_listening_heartbeats_mark_the_push_channel_and_break_statuses_are_mapped(): void
    {
        $token = Str::random(48);
        $device = $this->hikvision(['push_token_hash' => hash('sha256', $token), 'sync_cursor' => ['pulled_to' => '2026-10-14T03:30:00+00:00']]); // pulled 30 min ago
        $this->enroll($device, $this->scheduledEmployee(), '7');
        $url = '/attendance/push/hikvision/'.$token;
        $post = fn (array $payload) => $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])->post($url, ['AccessControllerEvent' => json_encode($payload)]);
        $event = fn (int $serial, string $status, string $time) => ['eventType' => 'AccessControllerEvent', 'dateTime' => "2026-10-14T{$time}+08:00",
            'AccessControllerEvent' => ['majorEventType' => 5, 'subEventType' => 38, 'serialNo' => $serial, 'employeeNoString' => '7', 'attendanceStatus' => $status, 'currentVerifyMode' => 'faceOrFpOrCardOrPw']];

        $post(['eventType' => 'heartBeat', 'dateTime' => '2026-10-14T11:59:30+08:00'])->assertOk();
        $this->assertNotNull($device->fresh()->sync_cursor['push_seen_at']);

        foreach ([[1, 'checkIn', '11:40:00'], [2, 'breakOut', '11:45:00'], [3, 'breakIn', '11:50:00'], [4, 'checkOut', '11:55:00']] as [$serial, $status, $time]) {
            $post($event($serial, $status, $time))->assertOk()->assertSee('<statusCode>1</statusCode>', false);
        }
        $post($event(4, 'checkOut', '11:55:00'))->assertOk(); // re-delivered: acknowledged, not stored again

        $this->assertSame(['check_in', 'break_out', 'break_in', 'check_out'], AttendanceEvent::orderBy('occurred_at')->pluck('event_type')->all());
        $this->assertSame(['push'], AttendanceDeviceSyncRun::whereIn('id', AttendanceEvent::pluck('attendance_device_sync_run_id'))->pluck('trigger')->unique()->values()->all());
        $this->assertNull(app(DeviceSyncService::class)->pullReason($device->fresh())); // push heard just now
    }
}

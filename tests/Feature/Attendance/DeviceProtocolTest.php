<?php

namespace Tests\Feature\Attendance;

use App\Http\Controllers\Device\HikvisionPushController;
use App\Models\AttendanceConnector;
use App\Models\AttendanceDevice;
use App\Models\AttendanceEvent;
use App\Services\Attendance\DeviceStreamService;
use App\Services\Attendance\DeviceSyncService;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * Vendor protocols without hardware. Hikvision responses are shaped like the
 * ISAPI responses used by the integration tested against the customer's
 * DS-K1T321 (branch feature/hikvision-api); ZKTeco requests follow the PUSH
 * (ADMS) ATTLOG format. These tests prove the adapters' handling of those
 * shapes, NOT compatibility with any particular device or firmware.
 */
class DeviceProtocolTest extends AttendanceTestCase
{
    private function hikvision(array $attributes = []): AttendanceDevice
    {
        return AttendanceDevice::create(array_merge([
            'name' => 'Lobby DS-K1T321', 'vendor' => 'hikvision', 'model' => 'DS-K1T321', 'serial_number' => 'HK-001',
            'timezone' => 'Asia/Manila', 'connection_method' => 'isapi_pull', 'host' => '192.0.2.10', 'port' => 80,
            'username' => 'admin', 'password' => 'S3cret-device-pass', 'page_size' => 2,
        ], $attributes));
    }

    private function acsRecord(int $serial, string $time, string $user, string $status): array
    {
        return ['major' => 5, 'minor' => 38, 'time' => $time, 'serialNo' => $serial, 'employeeNoString' => $user, 'name' => 'Person Name', 'cardNo' => '123456', 'attendanceStatus' => $status, 'currentVerifyMode' => 'fingerPrint'];
    }

    #[Test]
    public function hikvision_events_are_pulled_page_by_page_and_normalized(): void
    {
        $employee = $this->scheduledEmployee();
        $device = $this->hikvision();
        $this->enroll($device, $employee, '220464');
        Http::fake([
            '*/ISAPI/AccessControl/AcsEvent*' => Http::sequence()
                ->push(['AcsEvent' => ['searchID' => 'x', 'responseStatusStrg' => 'MORE', 'numOfMatches' => 2, 'totalMatches' => 3, 'InfoList' => [
                    $this->acsRecord(101, '2026-10-12T07:55:10+08:00', '220464', 'checkIn'),
                    $this->acsRecord(102, '2026-10-12T12:00:00+08:00', '220464', 'breakOut'),
                ]]])
                ->push(['AcsEvent' => ['searchID' => 'x', 'responseStatusStrg' => 'OK', 'numOfMatches' => 1, 'totalMatches' => 3,
                    'InfoList' => $this->acsRecord(103, '2026-10-12T17:01:00+08:00', '220464', 'checkOut')]]), // single record as object
        ]);

        $run = $this->sync($device, '2026-10-11T16:00:00', '2026-10-12T16:00:00');

        $this->assertSame(['succeeded', 3, 3], [$run->status, $run->fetched, $run->inserted]);
        Http::assertSentCount(2);
        Http::assertSent(fn (ClientRequest $r) => $r['AcsEventCond']['searchResultPosition'] === 2
            && $r['AcsEventCond']['major'] === 5 && $r['AcsEventCond']['minor'] === 38 && $r['AcsEventCond']['maxResults'] === 2
            && str_ends_with($r['AcsEventCond']['startTime'], '+08:00'));
        $event = AttendanceEvent::orderBy('id')->first();
        $this->assertSame(['device_pull', 'hikvision', '101', 'check_in', 'fingerPrint'], [$event->source, $event->vendor, $event->device_event_id, $event->event_type, $event->verify_mode]);
        $this->assertSame('2026-10-11T23:55:10+00:00', $event->occurred_at->toIso8601String());
        $this->assertArrayNotHasKey('name', $event->payload); // personal names and card numbers are not stored
        $this->assertArrayNotHasKey('cardNo', $event->payload);
    }

    #[Test]
    public function hikvision_connection_test_reads_device_info_and_clock(): void
    {
        $device = $this->hikvision(['model' => null]);
        Http::fake([
            '*/ISAPI/System/deviceInfo*' => Http::response(['DeviceInfo' => ['model' => 'DS-K1T321MFWX', 'serialNumber' => 'HK-001', 'firmwareVersion' => 'V3.x.x', 'firmwareReleasedDate' => 'build 0']]),
            '*/ISAPI/System/time*' => Http::response(['Time' => ['localTime' => '2026-10-14T12:01:00+08:00', 'timeZone' => 'CST-8:00:00']]),
        ]);

        $run = app(DeviceSyncService::class)->testConnection($device, $this->hr);

        $device->refresh();
        $this->assertSame('succeeded', $run->status);
        $this->assertSame(['DS-K1T321MFWX', 'V3.x.x build 0', 60, 'online'], [$device->model, $device->firmware_version, $device->clock_drift_seconds, $device->status]);
    }

    #[Test]
    public function hikvision_connection_test_accepts_xml_device_info_and_clock(): void
    {
        $device = $this->hikvision(['model' => null]);
        Http::fake([
            '*/ISAPI/System/deviceInfo*' => Http::response('<?xml version="1.0" encoding="UTF-8"?><DeviceInfo version="2.0" xmlns="http://www.isapi.org/ver20/XMLSchema"><model>DS-K1T321MFX</model><serialNumber>HK-001</serialNumber><firmwareVersion>V4.61.0</firmwareVersion><firmwareReleasedDate>build 260828</firmwareReleasedDate></DeviceInfo>', 200, ['Content-Type' => 'application/xml']),
            '*/ISAPI/System/time*' => Http::response('<?xml version="1.0" encoding="UTF-8"?><Time xmlns="http://www.isapi.org/ver20/XMLSchema"><timeMode>NTP</timeMode><localTime>2026-10-14T12:01:00+08:00</localTime><timeZone>CST-8:00:00</timeZone></Time>', 200, ['Content-Type' => 'application/xml']),
        ]);

        $run = app(DeviceSyncService::class)->testConnection($device, $this->hr);

        $device->refresh();
        $this->assertSame('succeeded', $run->status);
        $this->assertSame(['DS-K1T321MFX', 'V4.61.0 build 260828', 60], [$device->model, $device->firmware_version, $device->clock_drift_seconds]);
    }

    #[Test]
    public function an_unexpected_device_answer_names_what_was_received(): void
    {
        $device = $this->hikvision();
        Http::fake(['*/ISAPI/System/deviceInfo*' => Http::response(['ResponseStatus' => ['statusCode' => 4]])]);

        $run = app(DeviceSyncService::class)->testConnection($device, $this->hr);

        $this->assertSame(['failed', 'bad_response'], [$run->status, $run->error_code]);
        $this->assertStringContainsString('JSON keys: ResponseStatus', (string) $run->error_message);
    }

    #[Test]
    public function hikvision_errors_are_reported_without_credentials(): void
    {
        $device = $this->hikvision();
        Http::fake(['*' => Http::response('', 401)]);

        $run = $this->sync($device, '2026-10-11', '2026-10-12');

        $this->assertSame(['failed', 'auth_failed', 1], [$run->status, $run->error_code, $run->attempts]);
        $this->assertStringNotContainsString('S3cret', (string) $run->error_message);
        $this->assertStringNotContainsString('S3cret', (string) $device->fresh()->last_error);
    }

    #[Test]
    public function device_credentials_are_encrypted_and_never_serialized(): void
    {
        $device = $this->hikvision();

        $this->assertStringNotContainsString('S3cret-device-pass', (string) DB::table('attendance_devices')->value('password'));
        $this->assertSame('S3cret-device-pass', $device->fresh()->password);
        $this->assertArrayNotHasKey('password', $device->fresh()->toArray());
        $this->assertArrayNotHasKey('password', $device->fresh()->toSafeArray());
        $this->assertTrue($device->fresh()->toSafeArray()['has_credentials']);
    }

    #[Test]
    public function hikvision_push_requires_the_device_token_and_ignores_heartbeats(): void
    {
        $employee = $this->scheduledEmployee();
        $token = Str::random(48);
        $device = $this->hikvision(['connection_method' => 'isapi_push', 'push_token_hash' => hash('sha256', $token), 'sync_cursor' => ['pulled_to' => '2026-10-11T00:00:00+00:00']]);
        $this->enroll($device, $employee, '220464');
        $event = json_encode(['eventType' => 'AccessControllerEvent', 'dateTime' => '2026-10-12T08:00:00+08:00', 'shortSerialNumber' => 'HK',
            'AccessControllerEvent' => ['serialNo' => 900, 'majorEventType' => 5, 'subEventType' => 38, 'employeeNoString' => '220464', 'attendanceStatus' => 'checkIn', 'currentVerifyMode' => 'face', 'name' => 'X']]);
        $unconfigured = str_replace('"subEventType":38', '"subEventType":75', str_replace('"serialNo":900', '"serialNo":901', $event));

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10']); // the device's own address
        $this->post('/attendance/push/hikvision/'.Str::random(48), ['AccessControllerEvent' => $event])->assertStatus(401);
        $this->post('/attendance/push/hikvision/'.$token, ['AccessControllerEvent' => $event])->assertOk();
        $this->post('/attendance/push/hikvision/'.$token, ['AccessControllerEvent' => $event])->assertOk(); // replayed: stored once
        $this->post('/attendance/push/hikvision/'.$token, ['event_log' => json_encode(['eventType' => 'heartBeat'])])->assertOk();
        $this->post('/attendance/push/hikvision/'.$token, ['AccessControllerEvent' => $unconfigured])->assertOk(); // minor 75 not collected by default

        $this->assertSame(1, AttendanceEvent::count());
        $this->assertSame(['device_push', 'face'], [AttendanceEvent::first()->source, AttendanceEvent::first()->verify_mode]);
        $this->assertSame('online', $device->fresh()->status);
    }

    #[Test]
    public function a_polled_device_can_also_push_in_json_xml_or_multipart_and_each_event_is_stored_once(): void
    {
        $token = Str::random(48);
        $device = $this->hikvision(['push_token_hash' => hash('sha256', $token), 'sync_cursor' => ['pulled_to' => '2026-10-11T00:00:00+00:00']]); // isapi_pull: polling + push
        $this->enroll($device, $this->scheduledEmployee(), '220464');
        $url = '/attendance/push/hikvision/'.$token;
        $json = fn (int $serial, int $minor = 38) => json_encode(['eventType' => 'AccessControllerEvent', 'dateTime' => '2026-10-12T08:0'.($serial % 10).':00+08:00',
            'AccessControllerEvent' => ['serialNo' => $serial, 'majorEventType' => 5, 'subEventType' => $minor, 'employeeNoString' => '220464', 'attendanceStatus' => 'checkIn',
                'currentVerifyMode' => 'faceOrFpOrCardOrPw', 'name' => 'ERIC', 'cardNo' => '99887766', 'FaceRect' => ['x' => 1]]]);
        $xml = '<?xml version="1.0" encoding="UTF-8"?><EventNotificationAlert version="2.0" xmlns="http://www.isapi.org/ver20/XMLSchema"><dateTime>2026-10-12T08:03:00+08:00</dateTime><eventType>AccessControllerEvent</eventType>'
            .'<AccessControllerEvent><majorEventType>5</majorEventType><subEventType>38</subEventType><serialNo>703</serialNo><employeeNoString>220464</employeeNoString><attendanceStatus>checkOut</attendanceStatus><name>ERIC</name></AccessControllerEvent></EventNotificationAlert>';
        $device_ip = ['REMOTE_ADDR' => '192.0.2.10'];

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.99'])->post($url, ['AccessControllerEvent' => $json(701)])->assertStatus(403); // not the device
        $this->withServerVariables($device_ip)->call('POST', $url, [], [], [], ['CONTENT_TYPE' => 'application/json'], $json(701))->assertOk();
        $this->withServerVariables($device_ip)->call('POST', $url, [], [], [], ['CONTENT_TYPE' => 'application/xml'], $xml)->assertOk();
        $this->withServerVariables($device_ip)->post($url, ['event_log' => UploadedFile::fake()->createWithContent('event.json', $json(702)), 'Picture' => UploadedFile::fake()->image('face.jpg')])->assertOk();
        $this->withServerVariables($device_ip)->post($url, ['AccessControllerEvent' => $json(701)])->assertOk(); // repeated delivery
        $this->withServerVariables($device_ip)->post($url, ['AccessControllerEvent' => $json(704, 75)])->assertOk(); // code not collected
        $this->withServerVariables($device_ip)->call('POST', $url, [], [], [], ['CONTENT_TYPE' => 'text/plain'], 'not an event')->assertStatus(400);
        // A newly configured host receives the device's stored log: history behind the polling cursor is skipped.
        $old = str_replace('2026-10-12T08:05:00', '2025-11-07T12:54:38', $json(705));
        $this->withServerVariables($device_ip)->post($url, ['AccessControllerEvent' => $old])->assertOk();

        $events = AttendanceEvent::orderBy('device_event_id')->get();
        $this->assertSame(['701', '702', '703'], $events->pluck('device_event_id')->all()); // 704 (code 75) and 705 (history) not stored
        $log = file_get_contents(HikvisionPushController::logPath());
        $this->assertStringContainsString('sent 200', $log);
        $this->assertStringContainsString('outcome=replay_skipped serial=705', $log);
        $this->assertStringContainsString('sent 403', $log);
        $this->assertSame(['check_in', 'check_in', 'check_out'], $events->pluck('event_type')->all());
        $this->assertSame('2026-10-12T08:03:00+08:00', $events[2]->event_time_raw);
        foreach ($events as $event) {
            $this->assertSame([], array_intersect(['name', 'cardNo', 'FaceRect'], array_keys($event->payload)));
        }
        $log = file_get_contents(HikvisionPushController::logPath());
        $this->assertStringNotContainsString($token, $log);
        $this->assertStringNotContainsString('99887766', $log);
    }

    #[Test]
    public function a_push_that_cannot_be_stored_gets_a_server_error_so_the_device_retries(): void
    {
        $token = Str::random(48);
        $this->hikvision(['push_token_hash' => hash('sha256', $token), 'sync_cursor' => ['pulled_to' => '2026-10-11T00:00:00+00:00']]);
        $this->mock(DeviceStreamService::class, fn ($mock) => $mock->shouldReceive('handle')->andThrow(new \RuntimeException('database unavailable')));
        $event = json_encode(['eventType' => 'AccessControllerEvent', 'dateTime' => '2026-10-12T08:00:00+08:00',
            'AccessControllerEvent' => ['serialNo' => 801, 'majorEventType' => 5, 'subEventType' => 38, 'employeeNoString' => '220464', 'attendanceStatus' => 'checkIn']]);

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->post('/attendance/push/hikvision/'.$token, ['AccessControllerEvent' => $event])
            ->assertStatus(500);

        $this->assertSame(0, AttendanceEvent::count());
        $this->assertStringContainsString('sent 500', file_get_contents(HikvisionPushController::logPath()));
    }

    #[Test]
    public function a_stored_push_is_acknowledged_with_an_isapi_response_status(): void
    {
        $token = Str::random(48);
        $device = $this->hikvision(['push_token_hash' => hash('sha256', $token), 'sync_cursor' => ['pulled_to' => '2026-10-11T00:00:00+00:00']]);
        $this->enroll($device, $this->scheduledEmployee(), '220464');
        $event = json_encode(['eventType' => 'AccessControllerEvent', 'dateTime' => '2026-10-12T08:00:00+08:00',
            'AccessControllerEvent' => ['serialNo' => 802, 'majorEventType' => 5, 'subEventType' => 38, 'employeeNoString' => '220464', 'attendanceStatus' => 'checkIn']]);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])->post('/attendance/push/hikvision/'.$token, ['AccessControllerEvent' => $event]);

        $response->assertOk()->assertHeader('Content-Length', (string) strlen($response->getContent()));
        $this->assertStringContainsString('<statusCode>1</statusCode>', $response->getContent());
        $this->assertSame(1, AttendanceEvent::where('device_event_id', '802')->count());
        $this->assertStringContainsString('outcome=stored serial=802', file_get_contents(HikvisionPushController::logPath()));
    }

    #[Test]
    public function zkteco_push_uploads_attendance_logs_from_registered_devices_only(): void
    {
        $employee = $this->scheduledEmployee();
        $device = AttendanceDevice::create(['name' => 'ZK Gate', 'vendor' => 'zkteco', 'serial_number' => 'CKJE201360123', 'timezone' => 'Asia/Manila', 'connection_method' => 'zk_adms']);
        $this->enroll($device, $employee, '15');
        $body = "15\t2026-10-12 07:58:00\t0\t1\t0\t0\n15\t2026-10-12 17:05:00\t1\t1\t0\t0\n";

        $this->get('/iclock/cdata?SN=CKJE201360123&options=all&pushver=2.4.1')
            ->assertOk()->assertSee('TransFlag=TransData AttLog', false)->assertSee('TimeZone=8', false);
        $this->call('POST', '/iclock/cdata?SN=CKJE201360123&table=ATTLOG&Stamp=1', [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body)->assertOk()->assertSee('OK: 2');
        $this->call('POST', '/iclock/cdata?SN=CKJE201360123&table=ATTLOG&Stamp=1', [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body)->assertOk(); // re-upload
        $this->call('POST', '/iclock/cdata?SN=UNKNOWN&table=ATTLOG', [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body)->assertStatus(403);
        $this->get('/iclock/getrequest?SN=CKJE201360123')->assertOk()->assertSee('OK');

        $this->assertSame(2, AttendanceEvent::count());
        $this->assertSame(['check_in', 'check_out'], AttendanceEvent::orderBy('occurred_at')->pluck('event_type')->all());
        $this->assertSame('present', $this->day($employee, '2026-10-12')->status);
    }

    #[Test]
    public function connector_batches_must_be_signed_fresh_and_not_replayed(): void
    {
        $employee = $this->scheduledEmployee();
        $connector = AttendanceConnector::create(['name' => 'Branch PC', 'key' => 'conn_'.Str::random(20), 'secret' => 'connector-secret-value']);
        $device = $this->hikvision(['connection_method' => 'connector', 'attendance_connector_id' => $connector->id]);
        $other = $this->hikvision(['serial_number' => 'HK-002', 'connection_method' => 'connector']); // not this connector's
        $this->enroll($device, $employee, '7');
        $body = json_encode(['batches' => [
            ['device_id' => $device->id, 'events' => [['user' => '7', 'time' => '2026-10-12T08:00:00+08:00', 'type' => 'check_in', 'id' => '55']]],
            ['device_id' => $other->id, 'events' => [['user' => '7', 'time' => '2026-10-12T09:00:00+08:00', 'id' => '56']]],
        ]]);
        $headers = function (string $nonce, ?int $time = null, ?string $secret = null) use ($connector, $body) {
            $time ??= time();

            return ['X-Connector-Key' => $connector->key, 'X-Timestamp' => (string) $time, 'X-Nonce' => $nonce,
                'X-Signature' => hash_hmac('sha256', $time."\n".$nonce."\n".$body, $secret ?? 'connector-secret-value'), 'Content-Type' => 'application/json'];
        };
        $send = fn (array $h) => $this->call('POST', '/attendance/connector/events', [], [], [], collect($h)->mapWithKeys(fn ($v, $k) => ['HTTP_'.strtoupper(str_replace('-', '_', $k)) => $v])->put('CONTENT_TYPE', 'application/json')->all(), $body);

        $send($headers(Str::random(24), null, 'wrong-secret'))->assertStatus(401);
        $send($headers(Str::random(24), time() - 3600))->assertStatus(401);
        $nonce = Str::random(24);
        $send($headers($nonce))->assertOk()->assertJsonPath('results.0.inserted', 1)->assertJsonPath('results.1.error', 'unknown_device');
        $send($headers($nonce))->assertStatus(401); // replayed nonce

        $this->assertSame(1, AttendanceEvent::count());
        $this->assertSame('connector', AttendanceEvent::first()->source);
    }
}

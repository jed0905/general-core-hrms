<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceAdjustment;
use App\Models\AttendanceConnector;
use App\Models\AttendanceDevice;
use App\Models\AttendanceEvent;
use App\Models\Employee;
use App\Models\LegalEntity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;

/**
 * Attendance over HTTP: permission matrix per seeded role, every page renders,
 * device credentials / tokens and card numbers never reach a response,
 * supervisors see only their direct reports, adjustments need a second person,
 * and manual / CSV entries go through the shared pipeline.
 */
class AttendanceAccessTest extends AttendanceTestCase
{
    #[Test]
    public function attendance_pages_follow_the_permission_matrix(): void
    {
        $matrix = [
            'attendance.dashboard' => ['superadmin', 'hr_director', 'hr_manager', 'hr_staff', 'supervisor'],
            'attendance.daily.index' => ['superadmin', 'hr_director', 'hr_manager', 'hr_staff', 'supervisor'],
            'attendance.devices.index' => ['superadmin', 'hr_director', 'hr_manager', 'hr_staff'],
            'attendance.enrollments.index' => ['superadmin', 'hr_director', 'hr_manager', 'hr_staff'],
            'attendance.logs.index' => ['superadmin', 'hr_director', 'hr_manager', 'hr_staff'],
            'attendance.adjustments.index' => ['superadmin', 'hr_director', 'hr_manager', 'hr_staff'],
            'attendance.settings.index' => ['superadmin', 'hr_director', 'hr_manager'],
            'attendance.summaries.index' => ['superadmin', 'hr_director', 'hr_manager'],
        ];
        $roles = ['superadmin', 'hr_director', 'hr_manager', 'hr_staff', 'payroll', 'supervisor', 'employee'];
        $users = collect($roles)->mapWithKeys(fn ($role) => [$role => $this->userWithRole($role)]);

        foreach ($matrix as $route => $allowed) {
            foreach ($roles as $role) {
                $response = $this->actingAs($users[$role])->get(route($route));
                in_array($role, $allowed, true) ? $response->assertOk() : $response->assertForbidden();
            }
        }
    }

    #[Test]
    public function every_attendance_page_renders(): void
    {
        $admin = $this->userWithRole('superadmin');
        $device = $this->device();
        $employee = $this->scheduledEmployee();
        $this->enroll($device, $employee, '7');
        $this->punch($device, '7', ['2026-10-12 08:00' => 'check_in', '2026-10-12 17:00' => 'check_out']);

        $pages = [
            [route('attendance.dashboard', ['date' => '2026-10-12']), 'app/Attendance/Dashboard/Index'],
            [route('attendance.devices.index'), 'app/Attendance/Devices/Index'],
            [route('attendance.devices.show', $device), 'app/Attendance/Devices/Show'],
            [route('attendance.enrollments.index'), 'app/Attendance/Enrollments/Index'],
            [route('attendance.logs.index', ['date_from' => '2026-10-01']), 'app/Attendance/Logs/Index'],
            [route('attendance.daily.index', ['date_from' => '2026-10-12']), 'app/Attendance/Daily/Index'],
            [route('attendance.daily.employee', $employee), 'app/Attendance/Daily/Employee'],
            [route('attendance.adjustments.index'), 'app/Attendance/Adjustments/Index'],
            [route('attendance.roster.index'), 'app/Attendance/Roster/Index'],
            [route('attendance.settings.index'), 'app/Attendance/Settings/Index'],
            [route('attendance.summaries.index'), 'app/Attendance/Summaries/Index'],
        ];

        foreach ($pages as [$url, $component]) {
            $this->assertFileExists(resource_path("js/pages/{$component}.vue"));
            $this->actingAs($admin)->get($url)->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->component($component, false));
        }
    }

    #[Test]
    public function credentials_tokens_and_card_numbers_never_reach_a_page(): void
    {
        $admin = $this->userWithRole('superadmin');
        $this->actingAs($admin)->post(route('attendance.devices.store'), [
            'name' => 'Lobby', 'vendor' => 'hikvision', 'model' => 'DS-K1T321', 'serial_number' => 'HK-9', 'timezone' => 'Asia/Manila',
            'connection_method' => 'isapi_pull', 'host' => '192.0.2.5', 'port' => 80, 'username' => 'admin', 'password' => 'Very-Secret-Pass',
        ])->assertSessionHasNoErrors();
        $device = AttendanceDevice::where('serial_number', 'HK-9')->sole();
        $this->enroll($device, $this->scheduledEmployee(), '42', ['card_number' => 'CARD-998877']);

        $this->actingAs($admin)->put(route('attendance.devices.connection', $device), ['connection_method' => 'isapi_push'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('attendance.devices.push-token', $device))->assertSessionHas('push_token');
        $token = session('push_token');

        foreach ([route('attendance.devices.show', $device), route('attendance.devices.index'), route('attendance.enrollments.index')] as $url) {
            $html = $this->actingAs($admin)->get($url)->getContent();
            $this->assertStringNotContainsString('Very-Secret-Pass', $html);
            $this->assertStringNotContainsString('CARD-998877', $html);
            $this->assertStringNotContainsString(hash('sha256', $token), $html);
        }
        $this->assertSame('Very-Secret-Pass', $device->fresh()->password); // blank password on update kept the stored one
        $this->assertStringNotContainsString('Very-Secret-Pass', json_encode(DB::table('attendance_audit_logs')->pluck('changes')));
    }

    #[Test]
    public function registering_the_same_device_twice_is_refused(): void
    {
        $admin = $this->userWithRole('superadmin');
        $payload = ['name' => 'A', 'vendor' => 'zkteco', 'serial_number' => 'ZK-1', 'timezone' => 'Asia/Manila', 'connection_method' => 'zk_adms'];

        $this->actingAs($admin)->post(route('attendance.devices.store'), $payload)->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('attendance.devices.store'), $payload)->assertSessionHasErrors('serial_number');
        $this->actingAs($admin)->post(route('attendance.devices.store'), [...$payload, 'serial_number' => 'ZK-2', 'connection_method' => 'isapi_pull'])->assertSessionHasErrors('connection_method');
    }

    #[Test]
    public function a_connector_only_serves_devices_of_its_own_legal_entity_and_location(): void
    {
        $admin = $this->userWithRole('superadmin');
        $entity = fn (string $code) => LegalEntity::create(['code' => $code, 'name' => $code, 'legal_name' => $code, 'country_code' => 'XX', 'functional_currency' => 'USD', 'timezone' => 'UTC', 'locale' => 'en']);
        [$a, $b] = [$entity('ENT-A'), $entity('ENT-B')];
        $connector = AttendanceConnector::create(['name' => 'Site A', 'key' => 'conn_a', 'secret' => 'secret-a', 'legal_entity_id' => $a->id]);
        $payload = ['name' => 'Gate', 'vendor' => 'hikvision', 'serial_number' => 'HK-C1', 'timezone' => 'UTC', 'connection_method' => 'connector', 'attendance_connector_id' => $connector->id];

        $this->actingAs($admin)->post(route('attendance.devices.store'), [...$payload, 'legal_entity_id' => $b->id])->assertSessionHasErrors('attendance_connector_id');
        $this->actingAs($admin)->post(route('attendance.devices.store'), [...$payload, 'attendance_connector_id' => null])->assertSessionHasErrors('attendance_connector_id');
        $this->actingAs($admin)->post(route('attendance.devices.store'), [...$payload, 'legal_entity_id' => $a->id])->assertSessionHasNoErrors();

        $device = AttendanceDevice::where('serial_number', 'HK-C1')->sole();
        $this->actingAs($admin)->put(route('attendance.devices.update', $device), ['name' => 'Gate', 'timezone' => 'UTC', 'legal_entity_id' => $b->id])->assertSessionHasErrors('attendance_connector_id');
        $this->assertSame($a->id, $device->fresh()->legal_entity_id);
    }

    #[Test]
    public function only_users_who_can_configure_devices_may_set_credentials(): void
    {
        $staff = $this->userWithRole('hr_staff');
        $staff->givePermissionTo('attendance.device.manage');
        $device = $this->device();

        $this->actingAs($staff)->post(route('attendance.devices.store'), ['name' => 'X', 'vendor' => 'hikvision', 'timezone' => 'UTC', 'connection_method' => 'isapi_pull', 'password' => 'x'])->assertForbidden();
        $this->actingAs($staff)->put(route('attendance.devices.connection', $device), ['connection_method' => 'simulator'])->assertForbidden();
    }

    #[Test]
    public function a_supervisor_sees_only_their_direct_reports(): void
    {
        $supervisor = $this->userWithRole('supervisor');
        $report = $this->scheduledEmployee();
        $report->update(['supervisor_id' => $supervisor->employee_id]);
        $stranger = $this->scheduledEmployee();
        $device = $this->device();
        $this->enroll($device, $report, '1');
        $this->enroll($device, $stranger, '2');
        $this->punch($device, '1', ['2026-10-12 08:00' => 'check_in', '2026-10-12 17:00' => 'check_out']);
        $this->punch($device, '2', ['2026-10-12 08:00' => 'check_in', '2026-10-12 17:00' => 'check_out']);

        $this->actingAs($supervisor)->get(route('attendance.daily.index', ['date_from' => '2026-10-12']))
            ->assertInertia(fn (AssertableInertia $p) => $p->has('days.data', 1)->where('days.data.0.employee_id', $report->id));
        $this->actingAs($supervisor)->get(route('attendance.daily.employee', $report))->assertOk();
        $this->actingAs($supervisor)->get(route('attendance.daily.employee', $stranger))->assertForbidden();
    }

    #[Test]
    public function an_adjustment_must_be_decided_by_someone_else(): void
    {
        $employee = $this->scheduledEmployee();
        $device = $this->device();
        $this->enroll($device, $employee, '7');
        $this->punch($device, '7', ['2026-10-12 08:00' => 'check_in']);
        $director = $this->userWithRole('hr_director');

        $this->actingAs($this->hr)->post(route('attendance.adjustments.store'), [
            'employee_id' => $employee->id, 'work_date' => '2026-10-12', 'type' => 'add_punch', 'occurred_at' => '2026-10-12 17:00', 'event_type' => 'check_out', 'reason' => 'Forgot to punch out',
        ])->assertSessionHasNoErrors();
        $adjustment = AttendanceAdjustment::sole();
        $this->assertSame('incomplete', $this->day($employee, '2026-10-12')->status);

        $this->actingAs($this->hr)->put(route('attendance.adjustments.approve', $adjustment))->assertForbidden();
        $this->actingAs($director)->put(route('attendance.adjustments.reject', $adjustment), [])->assertSessionHasErrors('remarks');
        $this->actingAs($director)->put(route('attendance.adjustments.approve', $adjustment))->assertSessionHasNoErrors();
        $this->actingAs($director)->put(route('attendance.adjustments.approve', $adjustment->fresh()))->assertForbidden(); // already decided

        $this->assertSame('present', $this->day($employee, '2026-10-12')->status);
        $this->assertSame('adjustment', AttendanceEvent::find($adjustment->fresh()->created_event_id)->source);
        $this->assertSame(1, AttendanceEvent::where('source', 'simulator')->count()); // the original punch is untouched
    }

    #[Test]
    public function a_wrong_punch_is_excluded_by_an_approved_void_without_deleting_it(): void
    {
        $employee = $this->scheduledEmployee();
        $device = $this->device();
        $this->enroll($device, $employee, '7');
        $this->punch($device, '7', ['2026-10-12 08:00' => 'check_in', '2026-10-12 09:00' => 'check_out', '2026-10-12 17:00' => 'check_out']);
        $this->assertSame('incomplete', $this->day($employee, '2026-10-12')->status);
        $wrong = AttendanceEvent::orderBy('occurred_at')->skip(1)->first();

        $this->actingAs($this->hr)->post(route('attendance.adjustments.store'), ['employee_id' => $employee->id, 'work_date' => '2026-10-12', 'type' => 'void_punch', 'attendance_event_id' => $wrong->id, 'reason' => 'Accidental tap']);
        $this->actingAs($this->userWithRole('hr_director'))->put(route('attendance.adjustments.approve', AttendanceAdjustment::sole()));

        $this->assertSame(480, $this->day($employee, '2026-10-12')->worked_minutes);
        $this->assertNotNull(AttendanceEvent::find($wrong->id));
    }

    #[Test]
    public function manual_punches_and_csv_imports_are_labelled_and_deduplicated(): void
    {
        $employee = $this->scheduledEmployee();
        $this->actingAs($this->hr)->post(route('attendance.logs.store'), ['employee_id' => $employee->id, 'occurred_at' => '2026-10-12 08:00', 'event_type' => 'check_in', 'reason' => 'Device down'])->assertSessionHasNoErrors();
        $csv = "employee_number,date_time,type,device_serial\n{$employee->employee_number},2026-10-12 17:00,check_out,\n{$employee->employee_number},2026-10-12 17:00,check_out,\nNOPE,2026-10-12 17:00,check_out,\n";

        $this->actingAs($this->hr)->post(route('attendance.logs.import'), ['file' => UploadedFile::fake()->createWithContent('punches.csv', $csv)])
            ->assertSessionHas('success', '1 punches imported, 1 already stored.')
            ->assertSessionHas('error');

        $this->assertSame(['manual', 'import'], AttendanceEvent::orderBy('occurred_at')->pluck('source')->all());
        $this->assertSame($this->hr->id, AttendanceEvent::where('source', 'manual')->value('created_by'));
        $this->assertSame('present', $this->day($employee, '2026-10-12')->status);
    }

    #[Test]
    public function mapping_a_device_user_picks_up_their_earlier_unmatched_punches(): void
    {
        $employee = $this->scheduledEmployee();
        $device = $this->device();
        $this->punch($device, '88', ['2026-10-12 08:00' => 'check_in', '2026-10-12 17:00' => 'check_out']);

        $this->actingAs($this->hr)->post(route('attendance.enrollments.store'), ['attendance_device_id' => $device->id, 'employee_id' => $employee->id, 'device_user_id' => '88', 'enrolled_on' => '2026-10-01'])
            ->assertSessionHas('success', 'Mapping saved. 2 earlier punches were matched to the employee.');
        $this->actingAs($this->hr)->post(route('attendance.enrollments.store'), ['attendance_device_id' => $device->id, 'employee_id' => Employee::factory()->create()->id, 'device_user_id' => '88'])
            ->assertSessionHasErrors('device_user_id');
        $this->assertSame('present', $this->day($employee, '2026-10-12')->status);
    }

    #[Test]
    public function the_connector_command_spools_and_delivers_signed_batches(): void
    {
        $connector = AttendanceConnector::create(['name' => 'Branch', 'key' => 'conn_test', 'secret' => 'top-secret']);
        $config = tempnam(sys_get_temp_dir(), 'conn');
        $spool = sys_get_temp_dir().'/hrms-connector-test-'.uniqid();
        file_put_contents($config, json_encode(['hrms_url' => 'https://hrms.example.invalid', 'connector_key' => $connector->key, 'connector_secret' => 'top-secret', 'spool_dir' => $spool,
            'devices' => [['hrms_device_id' => 77, 'vendor' => 'simulator', 'connection_method' => 'simulator', 'timezone' => 'Asia/Manila',
                'simulation' => ['events' => [['user' => '5', 'time' => '2026-10-14 08:00:00', 'type' => 'check_in', 'id' => 'c1']]]]]]));
        Http::fake(['hrms.example.invalid/*' => Http::sequence()->push('down', 503)->push(['results' => []], 200)]);

        $this->artisan('attendance:connector', ['config' => $config, '--once' => true])->assertSuccessful();
        $this->assertCount(1, glob("{$spool}/batch-*.json")); // HRMS returned 503: batch kept
        $this->artisan('attendance:connector', ['config' => $config, '--once' => true])->assertSuccessful();

        $this->assertCount(0, glob("{$spool}/batch-*.json"));
        Http::assertSent(function ($request) {
            $body = $request->body();

            return $request->hasHeader('X-Signature')
                && hash_equals(hash_hmac('sha256', $request->header('X-Timestamp')[0]."\n".$request->header('X-Nonce')[0]."\n".$body, 'top-secret'), $request->header('X-Signature')[0])
                && ! str_contains($body, 'password');
        });
    }
}

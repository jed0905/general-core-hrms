<?php

namespace Tests\Feature\Time;

use App\Models\Employee;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\Feature\Reports\ReportsTestCase;

/**
 * Time → Time Logs: organization-wide raw punches for users with attendance.view.
 * Uses the same AttendanceLogService as Reports → Attendance Log.
 */
class TimeLogsTest extends ReportsTestCase
{
    private Location $main;

    private Location $north;

    protected function setUp(): void
    {
        parent::setUp(); // clock: 2026-10-07; default range Oct 1–7

        $this->main = Location::create(['address' => 'Main Office', 'city' => 'Pasig City']);
        $this->north = Location::create(['address' => 'North Branch', 'city' => 'Quezon City']);
        $this->ana->update(['location_id' => $this->main->id]);
        $this->ben->update(['location_id' => $this->north->id]);

        $this->punch($this->ana, 'BIO-ANA', '2026-10-05', '08:01:00');
        $this->punch($this->ana, 'BIO-ANA', '2026-10-05', '17:04:00', 'check out');
        $this->punch($this->ben, 'BIO-BEN', '2026-10-06', '07:58:00');
        $this->punch($this->cara, 'BIO-CARA', '2026-10-06', '08:20:00');
        $this->punch(null, 'BIO-X', '2026-10-06', '06:00:00');
        $this->punch($this->ana, 'BIO-ANA', '2026-09-15', '08:00:00'); // outside the default range
    }

    private function logs(User $user, array $params = []): array
    {
        return $this->actingAs($user)->get(route('time.time-logs.index', $params))->assertOk()
            ->viewData('page')['props']['logs'];
    }

    #[Test]
    public function authorized_hr_user_can_view_time_logs(): void
    {
        $this->actingAs($this->reportUser('hr_staff'))
            ->get(route('time.time-logs.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('app/Time/TimeLogs/Index', false)
                ->where('logs.total', 5)
                ->where('filters.date_from', '2026-10-01')
                ->where('filters.date_to', '2026-10-07')
                ->where('can.export', true));
    }

    #[Test]
    public function employees_and_self_service_permissions_get_403(): void
    {
        $this->actingAs($this->userWithRole('employee'))->get(route('time.time-logs.index'))->assertForbidden();
        $this->actingAs($this->reportUser('payroll'))->get(route('time.time-logs.index'))->assertForbidden();

        $selfService = Role::create(['name' => 'own_attendance', 'guard_name' => 'web'])->givePermissionTo(['attendance.view_own', 'attendance.export_own']);
        $this->actingAs(User::factory()->create(['employee_id' => $this->ana->id])->assignRole($selfService))
            ->get(route('time.time-logs.index'))
            ->assertForbidden();
    }

    #[Test]
    public function date_range_filters_the_logs(): void
    {
        $hr = $this->reportUser('hr_director');

        $this->assertSame(['2026-09-15'], collect($this->logs($hr, ['date_from' => '2026-09-01', 'date_to' => '2026-09-30'])['data'])->pluck('date')->all());
        $this->assertSame(3, $this->logs($hr, ['date_from' => '2026-10-06', 'date_to' => '2026-10-06'])['total']);

        $this->actingAs($hr)->get(route('time.time-logs.index', ['date_from' => '2026-01-01', 'date_to' => '2026-10-07']))
            ->assertSessionHasErrors('date_to');
    }

    #[Test]
    public function employee_filter_matches_name_number_or_biometric_id(): void
    {
        $hr = $this->reportUser('hr_director');

        $this->assertSame(['Reyes, Ana', 'Reyes, Ana'], collect($this->logs($hr, ['search' => 'Reyes'])['data'])->pluck('employee')->all());
        $this->assertSame(['Cruz, Ben'], collect($this->logs($hr, ['search' => $this->ben->employee_number])['data'])->pluck('employee')->all());
        $this->assertSame(['Unregistered ID'], collect($this->logs($hr, ['search' => 'BIO-X'])['data'])->pluck('employee')->all());
    }

    #[Test]
    public function department_filter_includes_sub_departments_by_default(): void
    {
        $hr = $this->reportUser('hr_director');

        $this->assertSame(['Cruz, Ben', 'Reyes, Ana', 'Reyes, Ana'], collect($this->logs($hr, ['department_id' => $this->it->id])['data'])->pluck('employee')->sort()->values()->all());
        $this->assertSame(['Reyes, Ana', 'Reyes, Ana'], collect($this->logs($hr, ['department_id' => $this->it->id, 'include_sub_departments' => 0])['data'])->pluck('employee')->all());
        $this->assertSame(['Diaz, Cara'], collect($this->logs($hr, ['department_id' => $this->hr->id])['data'])->pluck('employee')->all());
    }

    #[Test]
    public function location_filter_uses_the_employees_location(): void
    {
        $hr = $this->reportUser('hr_director');

        $this->assertSame(['Cruz, Ben'], collect($this->logs($hr, ['location_id' => $this->north->id])['data'])->pluck('employee')->all());
        $this->assertCount(2, $this->logs($hr, ['location_id' => $this->main->id])['data']);
    }

    #[Test]
    public function direction_and_device_filters(): void
    {
        $hr = $this->reportUser('hr_director');

        $this->assertSame(['17:04:00'], collect($this->logs($hr, ['log_direction' => 'check out'])['data'])->pluck('time')->all());
        $this->assertSame(5, $this->logs($hr, ['device_name' => 'Lobby'])['total']);
        $this->assertSame(0, $this->logs($hr, ['device_name' => 'Nowhere'])['total']);
    }

    #[Test]
    public function results_are_paginated_on_the_server(): void
    {
        foreach (range(1, 30) as $i) {
            $this->punch($this->cara, 'BIO-CARA', '2026-10-02', sprintf('09:%02d:00', $i));
        }

        $hr = $this->reportUser('hr_director');
        $first = $this->logs($hr);
        $second = $this->logs($hr, ['page' => 2]);
        $wide = $this->logs($hr, ['per_page' => 50]);

        $this->assertSame([35, 25, 25, 2], [$first['total'], count($first['data']), $first['per_page'], $first['last_page']]);
        $this->assertCount(10, $second['data']);
        $this->assertEmpty(array_intersect(array_column($first['data'], 'id'), array_column($second['data'], 'id')));
        $this->assertCount(35, $wide['data']);
        $this->actingAs($hr)->get(route('time.time-logs.index', ['per_page' => 1000]))->assertSessionHasErrors('per_page');
    }

    #[Test]
    public function rows_carry_the_employee_department_location_and_device_details(): void
    {
        $row = collect($this->logs($this->reportUser('hr_director'), ['search' => 'Cruz'])['data'])->first();

        $this->assertSame([
            'date' => '2026-10-06',
            'time' => '07:58:00',
            'direction' => 'in',
            'device' => 'Lobby',
            'device_serial' => 'SN-001',
            'card_no' => '0',
            'person_name' => 'Ben',
            'biometric_id' => 'BIO-BEN',
            'employee_id' => $this->ben->id,
            'employee_number' => $this->ben->employee_number,
            'employee' => 'Cruz, Ben',
            'department' => 'Information Technology › Development',
            'location' => 'North Branch, Quezon City',
        ], collect($row)->except('id')->all());
    }

    #[Test]
    public function query_count_does_not_grow_with_the_number_of_rows(): void
    {
        $hr = $this->reportUser('hr_director');
        $count = function () use ($hr) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->logs($hr, ['per_page' => 100]);
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $count(); // warm the permission cache
        $few = $count();

        foreach (range(1, 40) as $i) {
            $employee = Employee::factory()->create(['department_id' => $this->dev->id, 'location_id' => $this->north->id]);
            $this->punch($employee, "BIO-N{$i}", '2026-10-03', '08:00:00');
        }

        $this->assertSame($few, $count(), 'one joined query regardless of rows (no N+1)');
    }

    #[Test]
    public function time_logs_and_the_attendance_log_report_share_one_query(): void
    {
        $hr = $this->reportUser('hr_director');
        $filters = ['department_id' => $this->it->id, 'log_direction' => 'in'];

        $timeLogs = collect($this->logs($hr, $filters)['data'])->pluck('id')->sort()->values()->all();
        $report = collect($this->rowsOf($hr, 'attendance-log', $filters))->pluck('id')->sort()->values()->all();

        $this->assertSame($report, $timeLogs);
        $this->assertCount(2, $report);

        // Sorting the report (sort/direction = asc|desc) must not be mistaken for the punch filter.
        $this->assertCount(3, $this->rowsOf($hr, 'attendance-log', ['department_id' => $this->it->id, 'sort' => 'date', 'direction' => 'asc']));
        $this->actingAs($hr)->get(route('reports.attendance-log.print', $filters))->assertOk()->assertSee('Cruz, Ben');
    }

    #[Test]
    public function export_links_follow_report_permissions(): void
    {
        // Supervisors hold attendance.view but not report.export.
        $this->actingAs($this->reportUser('supervisor'))->get(route('time.time-logs.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('can.export', false));
    }

    #[Test]
    public function my_attendance_stays_own_records_only(): void
    {
        $ana = User::factory()->create(['employee_id' => $this->ana->id])->assignRole('employee');

        $this->actingAs($ana)->get(route('time.my-attendance.index', ['from' => '2026-10-01', 'to' => '2026-10-07']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('app/Time/MyAttendance/Index', false)
                ->where('logs.total', 2));
    }
}

<?php

namespace Tests\Feature\Attendance;

use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;

/**
 * Attendance reports read calculated days and sync history, within the
 * viewer's attendance scope.
 */
class AttendanceReportsTest extends AttendanceTestCase
{
    #[Test]
    public function the_daily_and_totals_reports_show_calculated_attendance_in_scope(): void
    {
        $device = $this->device();
        $mine = $this->scheduledEmployee();
        $other = $this->scheduledEmployee();
        $supervisor = $this->userWithRole('supervisor');
        $supervisor->givePermissionTo('report.attendance');
        $mine->update(['supervisor_id' => $supervisor->employee_id]);
        $this->enroll($device, $mine, '1');
        $this->enroll($device, $other, '2');
        $this->punch($device, '1', ['2026-10-12 08:30' => 'check_in', '2026-10-12 17:00' => 'check_out']);
        $this->punch($device, '2', ['2026-10-12 08:00' => 'check_in']);
        $query = ['date_from' => '2026-10-12', 'date_to' => '2026-10-12'];

        $this->actingAs($this->hr)->get(route('reports.attendance-daily.show', [...$query, 'attendance_status' => 'exceptions']))
            ->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->has('rows.data', 2));
        $this->actingAs($supervisor)->get(route('reports.attendance-daily.show', $query))
            ->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->has('rows.data', 1)->where('rows.data.0.late', '0:30'));
        $this->actingAs($this->hr)->get(route('reports.attendance-totals.show', $query))
            ->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->has('rows.data', 2));
        $this->actingAs($this->hr)->get(route('reports.attendance-daily.excel', $query))->assertOk();
    }

    #[Test]
    public function the_device_sync_report_needs_device_access(): void
    {
        $this->sync($this->device(['simulation' => ['online' => false]]), '2026-10-11', '2026-10-12');
        $supervisor = $this->userWithRole('supervisor');
        $supervisor->givePermissionTo('report.attendance');

        $this->actingAs($this->hr)->get(route('reports.attendance-device-sync.show', ['date_from' => '2026-10-14', 'date_to' => '2026-10-14']))
            ->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->has('rows.data', 1)->where('rows.data.0.status', 'failed'));
        $this->actingAs($supervisor)->get(route('reports.attendance-device-sync.show'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('reports.index'))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('categories', fn ($c) => ! collect($c)->pluck('reports')->flatten(1)->pluck('key')->contains('attendance-device-sync')));
    }
}

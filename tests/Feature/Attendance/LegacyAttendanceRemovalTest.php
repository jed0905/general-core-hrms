<?php

namespace Tests\Feature\Attendance;

use App\Models\Employee;
use App\Models\EmployeeWorkSchedule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;

/**
 * The attendance module is the only attendance system: the legacy pages,
 * reports, models, tables and permissions are gone, old URLs redirect to the
 * attendance module, and self-service reads the engine's results.
 */
class LegacyAttendanceRemovalTest extends AttendanceTestCase
{
    #[Test]
    public function obsolete_legacy_routes_no_longer_exist(): void
    {
        foreach (['time.time-logs.index', 'time.my-attendance.index', 'time.my-attendance.export', 'attendance.logs.import-legacy', 'reports.attendance-summary.show'] as $name) {
            $this->assertFalse(Route::has($name), "{$name} should no longer exist");
        }

        $this->actingAs($this->userWithRole('employee'))->get('/time/my-attendance')->assertNotFound();
        $this->actingAs($this->hr)->get('/time/time-logs')->assertNotFound();
    }

    #[Test]
    public function navigation_links_only_to_the_attendance_module(): void
    {
        $sidebar = file_get_contents(resource_path('js/layouts/SidebarLayout.vue'));

        foreach (['time.my-attendance', 'time.time-logs', 'reports.attendance-summary', 'dailytimerecord'] as $obsolete) {
            $this->assertStringNotContainsString($obsolete, $sidebar);
        }
        foreach (['attendance.my.index', 'attendance.logs.index', 'attendance.daily.index', 'reports.attendance-daily.show'] as $route) {
            $this->assertStringContainsString("\"{$route}\"", $sidebar);
            $this->assertTrue(Route::has($route), "{$route} is linked but not registered");
        }
    }

    #[Test]
    public function self_service_attendance_shows_engine_results_from_the_new_tables(): void
    {
        $user = $this->userWithRole('employee');
        $employee = Employee::findOrFail($user->employee_id);
        $this->scheduledEmployee(); // someone else, never shown
        $schedule = EmployeeWorkSchedule::where('employee_id', '<>', $employee->id)->firstOrFail();
        EmployeeWorkSchedule::create(['employee_id' => $employee->id, 'work_schedule_id' => $schedule->work_schedule_id, 'effective_from' => '2026-01-01', 'is_primary' => true]);
        $device = $this->device();
        $this->enroll($device, $employee, '501');
        $this->punch($device, '501', ['2026-10-13 08:20' => 'check_in', '2026-10-13 17:00' => 'check_out']);

        $this->actingAs($user)->get(route('attendance.my.index', ['date_from' => '2026-10-13', 'date_to' => '2026-10-13']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('app/Attendance/My/Index')
                ->has('days', 1)
                ->where('days.0.status', 'late')
                ->where('days.0.late_minutes', 20)
                ->has('events', 2));

        $this->actingAs($user)->get(route('attendance.my.index', ['date_from' => '2026-01-01', 'date_to' => '2026-10-13']))->assertSessionHasErrors('date_to');
    }

    #[Test]
    public function the_legacy_attendance_tables_and_permissions_no_longer_exist(): void
    {
        $this->assertFalse(Schema::hasTable('employee_attendance_logs'));
        $this->assertFalse(Schema::hasTable('employee_biometric_ids'));
        foreach (['attendance_devices', 'attendance_connectors', 'biometric_enrollments', 'attendance_events', 'attendance_days', 'attendance_adjustments', 'attendance_period_summaries', 'attendance_audit_logs'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} is missing");
        }
        $this->assertSame(0, Permission::whereIn('name', ['attendance.update', 'attendance.delete', 'attendance.reprocess'])->count());
    }

    #[Test]
    public function no_application_code_refers_to_the_legacy_attendance_system(): void
    {
        $files = collect(['app', 'routes', 'resources/js', 'resources/views', 'database/seeders', 'database/factories', 'config'])
            ->flatMap(fn ($dir) => File::allFiles(base_path($dir)));

        foreach ($files as $file) {
            $this->assertDoesNotMatchRegularExpression(
                '/EmployeeAttendanceLog|EmployeeBiometricId|employee_attendance_logs|employee_biometric_ids|import-legacy-logs/',
                $file->getContents(),
                "{$file->getRelativePathname()} still refers to the legacy attendance system"
            );
        }
    }
}

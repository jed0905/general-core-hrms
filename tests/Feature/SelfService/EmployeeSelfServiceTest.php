<?php

namespace Tests\Feature\SelfService;

use App\Models\Employee;
use App\Models\EmployeeAttendanceLog;
use App\Models\EmployeeBiometricId;
use App\Models\EmployeeWorkSchedule;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkSchedule;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\Feature\Leave\LeaveTestCase;

/**
 * Employee self-service: `*_own` permissions reach only the user's own
 * employee record, and never replace the organization-wide permissions.
 */
class EmployeeSelfServiceTest extends LeaveTestCase
{
    private User $me;

    private Employee $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->me = $this->userWithRole('employee', ['mobile_no' => '0917-000-0000']);
        $this->other = $this->employeeOf($this->userWithRole('employee', ['mobile_no' => '0918-111-1111']));
    }

    // ---------------------------------------------------------------
    // Employee information
    // ---------------------------------------------------------------

    #[Test]
    public function employee_can_view_own_information(): void
    {
        $this->actingAs($this->me)
            ->get(route('people.my-profile.show'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('app/People/MyProfile/Index', false)
                ->where('profile.id', $this->me->employee_id)
                ->where('profile.contact.mobile_no', '0917-000-0000')
                ->where('can.update', true));
    }

    #[Test]
    public function employee_cannot_view_another_employees_information(): void
    {
        $this->actingAs($this->me)->get(route('people.employee.show', $this->other))->assertForbidden();
        $this->actingAs($this->me)->get(route('people.employee.index'))->assertForbidden();

        // Parameter manipulation: the profile page ignores any employee id it is given.
        $this->actingAs($this->me)
            ->get(route('people.my-profile.show', ['employee' => $this->other->id, 'employee_id' => $this->other->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('profile.id', $this->me->employee_id));

        $this->assertFalse($this->me->can('view', $this->other));
        $this->assertTrue($this->me->can('view', $this->employeeOf($this->me)));
    }

    #[Test]
    public function employee_updates_only_own_contact_details_whatever_the_payload_says(): void
    {
        $mine = $this->employeeOf($this->me);

        $this->actingAs($this->me)->put(route('people.my-profile.update'), [
            'mobile_no' => '0999-222-3333',
            'city' => 'Baguio',
            // none of these may be applied
            'employee_id' => $this->other->id,
            'id' => $this->other->id,
            'emp_first_name' => 'Hacked',
            'status' => 'terminated',
            'supervisor_id' => $this->other->id,
            'work_email' => 'ceo@example.com',
            'department_id' => 999,
        ])->assertSessionHasNoErrors()->assertRedirect(route('people.my-profile.show'));

        $mine->refresh();
        $this->assertSame('0999-222-3333', $mine->mobile_no);
        $this->assertSame('Baguio', $mine->city);
        $this->assertNotSame('Hacked', $mine->emp_first_name);
        $this->assertSame('active', $mine->status);
        $this->assertNull($mine->supervisor_id);
        $this->assertNull($mine->work_email);

        $this->assertSame('0918-111-1111', $this->other->fresh()->mobile_no, "another employee's record changed");
    }

    #[Test]
    public function view_own_without_update_own_is_read_only(): void
    {
        $readOnly = Role::create(['name' => 'directory_only', 'guard_name' => 'web'])->givePermissionTo('employee.view_own');
        $user = User::factory()->create(['employee_id' => Employee::factory()->create()->id])->assignRole($readOnly);

        $this->actingAs($user)->get(route('people.my-profile.show'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('can.update', false));
        $this->actingAs($user)->put(route('people.my-profile.update'), ['mobile_no' => '1'])->assertForbidden();
    }

    #[Test]
    public function account_without_employee_record_gets_an_empty_profile_and_cannot_update(): void
    {
        $user = User::factory()->create()->assignRole('employee');

        $this->actingAs($user)->get(route('people.my-profile.show'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('profile', null)->where('can.update', false));
        $this->actingAs($user)->put(route('people.my-profile.update'), ['mobile_no' => '1'])->assertForbidden();
    }

    // ---------------------------------------------------------------
    // Work schedule
    // ---------------------------------------------------------------

    #[Test]
    public function employee_sees_only_own_work_schedule(): void
    {
        $mine = $this->assignSchedule($this->employeeOf($this->me), 'DAY');
        $theirs = $this->assignSchedule($this->other, 'NIGHT');

        $this->actingAs($this->me)
            ->get(route('time.my-schedule.index', ['employee_id' => $this->other->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('app/Time/MySchedule/Index', false)
                ->has('schedules', 1)
                ->where('schedules.0.id', $mine->id)
                ->where('schedules.0.is_current', true)
                ->where('schedules.0.work_schedule.code', 'DAY')
                ->has('schedules.0.work_schedule.days', 5));

        $this->assertNotSame($theirs->id, $mine->id);
    }

    #[Test]
    public function employee_cannot_view_another_employees_schedule(): void
    {
        $this->assignSchedule($this->other, 'NIGHT');

        $this->actingAs($this->me)->get(route('time.employee-schedules.index'))->assertForbidden();
        $this->assertFalse($this->me->can('viewWorkSchedule', $this->other));
        $this->assertTrue($this->me->can('viewWorkSchedule', $this->employeeOf($this->me)));
    }

    // ---------------------------------------------------------------
    // Attendance
    // ---------------------------------------------------------------

    #[Test]
    public function employee_sees_and_exports_only_own_attendance(): void
    {
        $this->punch($this->employeeOf($this->me), 'BIO-ME', '08:01:00');
        $this->punch($this->other, 'BIO-OTHER', '07:30:00');

        $range = ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString()];

        $this->actingAs($this->me)
            ->get(route('time.my-attendance.index', $range + ['employee_id' => 'BIO-OTHER']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('app/Time/MyAttendance/Index', false)
                ->where('logs.total', 1)
                ->where('logs.data.0.auth_time', '08:01:00')
                ->where('can.export', true));

        $csv = $this->actingAs($this->me)
            ->get(route('time.my-attendance.export', $range + ['employee_id' => $this->other->id]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('08:01:00', $csv);
        $this->assertStringNotContainsString('07:30:00', $csv);
    }

    #[Test]
    public function employee_cannot_view_another_employees_attendance(): void
    {
        $this->punch($this->other, 'BIO-OTHER', '07:30:00');

        // No device id of their own: nothing is shown, not somebody else's punches.
        $this->actingAs($this->me)->get(route('time.my-attendance.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('logs.total', 0)->where('hasBiometricId', false));

        $this->assertFalse($this->me->can('viewAttendance', $this->other));
        $this->assertFalse($this->me->can('exportAttendance', $this->other));
    }

    #[Test]
    public function exporting_needs_its_own_permission(): void
    {
        $role = Role::create(['name' => 'view_only_attendance', 'guard_name' => 'web'])->givePermissionTo('attendance.view_own');
        $user = User::factory()->create(['employee_id' => Employee::factory()->create()->id])->assignRole($role);

        $this->actingAs($user)->get(route('time.my-attendance.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('can.export', false));
        $this->actingAs($user)->get(route('time.my-attendance.export'))->assertForbidden();
    }

    // ---------------------------------------------------------------
    // Leave and administrative boundaries
    // ---------------------------------------------------------------

    #[Test]
    public function employee_can_use_existing_leave_self_service(): void
    {
        $this->actingAs($this->me)->get(route('leave.applications.index'))->assertOk();
        $this->actingAs($this->me)->get(route('leave.applications.my-balances'))->assertOk();
        $this->actingAs($this->me)->get(route('leave.applications.history'))->assertOk();
    }

    #[Test]
    public function employee_cannot_reach_hr_administration(): void
    {
        $adminEndpoints = [
            route('people.employee.index'),
            route('people.employee.show', $this->other),
            route('people.employee.create'),
            route('people.job-title.index'),
            route('time.shifts.index'),
            route('time.work-schedules.index'),
            route('time.employee-schedules.index'),
            route('time.holidays.index'),
            route('leave.balances.index'),
            route('leave.approvals.index'),
            route('leave.config.types.index'),
            route('leave.config.workflows.index'),
            route('administration.user.index'),
            route('administration.role.index'),
            route('administration.organization.index'),
        ];

        foreach ($adminEndpoints as $url) {
            $this->actingAs($this->me)->get($url)->assertForbidden();
        }

        $this->actingAs($this->me)
            ->put(route('people.employee.update', $this->other), ['emp_first_name' => 'X'])
            ->assertForbidden();
    }

    #[Test]
    public function an_administrative_permission_opens_the_organization_wide_module(): void
    {
        $this->me->givePermissionTo(['employee.view', 'employee_work_schedule.view']);

        $this->actingAs($this->me)->get(route('people.employee.index'))->assertOk();
        $this->actingAs($this->me)->get(route('people.employee.show', $this->other))->assertOk();
        $this->actingAs($this->me)->get(route('time.employee-schedules.index'))->assertOk();
        $this->assertTrue($this->me->can('view', $this->other));
        $this->assertTrue($this->me->can('viewWorkSchedule', $this->other));
    }

    #[Test]
    public function multiple_roles_give_the_union_of_capabilities(): void
    {
        $user = $this->userWithRole('employee');
        $user->assignRole('supervisor');

        // from employee/supervisor self-service
        $this->actingAs($user)->get(route('people.my-profile.show'))->assertOk();
        $this->actingAs($user)->get(route('time.my-schedule.index'))->assertOk();
        // from supervisor only
        $this->actingAs($user)->get(route('leave.approvals.index'))->assertOk();
        $this->actingAs($user)->get(route('people.employee.index'))->assertOk();
        // held by neither
        $this->actingAs($user)->get(route('leave.balances.index'))->assertForbidden();
        $this->actingAs($user)->get(route('administration.user.index'))->assertForbidden();
    }

    #[Test]
    public function self_service_is_permission_based_not_role_based(): void
    {
        // A custom role with a different name gets exactly what its permissions say.
        $role = Role::create(['name' => 'contractor', 'guard_name' => 'web'])
            ->givePermissionTo(['employee.view_own', 'work_schedule.view_own']);
        $user = User::factory()->create(['employee_id' => Employee::factory()->create()->id])->assignRole($role);

        $this->actingAs($user)->get(route('people.my-profile.show'))->assertOk();
        $this->actingAs($user)->get(route('time.my-schedule.index'))->assertOk();
        $this->actingAs($user)->get(route('time.my-attendance.index'))->assertForbidden();
        $this->actingAs($user)->get(route('leave.applications.index'))->assertForbidden();
    }

    #[Test]
    public function direct_urls_are_protected_when_the_navigation_item_is_hidden(): void
    {
        $role = Role::create(['name' => 'no_self_service', 'guard_name' => 'web'])->givePermissionTo('dashboard.view');
        $user = User::factory()->create(['employee_id' => Employee::factory()->create()->id])->assignRole($role);

        // The navigation is built from the shared permissions: none of the self-service ones are there.
        $this->actingAs($user)->get(route('leave.applications.index'))->assertForbidden();
        $shared = collect($this->actingAs($user)->get(route('dashboard.index'))->viewData('page')['props']['auth']['permissions']);
        $this->assertContains('dashboard.view', $shared->all());
        foreach (['employee.view_own', 'work_schedule.view_own', 'attendance.view_own', 'leave.view_own'] as $permission) {
            $this->assertNotContains($permission, $shared->all());
        }

        foreach ([
            ['get', route('people.my-profile.show')],
            ['put', route('people.my-profile.update')],
            ['get', route('time.my-schedule.index')],
            ['get', route('time.my-attendance.index')],
            ['get', route('time.my-attendance.export')],
        ] as [$method, $url]) {
            $this->actingAs($user)->{$method}($url)->assertForbidden();
        }
    }

    #[Test]
    public function every_seeded_role_has_the_self_service_set(): void
    {
        foreach (['superadmin', 'hr_director', 'hr_manager', 'hr_staff', 'payroll', 'supervisor', 'employee'] as $name) {
            foreach (['employee.view_own', 'employee.update_own', 'work_schedule.view_own', 'attendance.view_own', 'attendance.export_own'] as $permission) {
                $this->assertTrue(Role::findByName($name)->hasPermissionTo($permission), "{$name} lacks {$permission}");
            }
        }

        // The employee role stays self-service only.
        foreach (['employee.view', 'employee.update', 'employee_work_schedule.view', 'attendance.view', 'attendance.export'] as $permission) {
            $this->assertFalse(Role::findByName('employee')->hasPermissionTo($permission), "employee has {$permission}");
        }
    }

    private function assignSchedule(Employee $employee, string $code): EmployeeWorkSchedule
    {
        $shift = Shift::create([
            'name' => "{$code} shift", 'code' => "{$code}-S", 'start_time' => '08:00', 'end_time' => '17:00', 'required_hours' => 8,
        ]);
        $schedule = WorkSchedule::create(['name' => "{$code} schedule", 'code' => $code]);

        foreach (range(1, 5) as $day) {
            $schedule->days()->create(['day_of_week' => $day, 'shift_id' => $shift->id, 'is_working_day' => true]);
        }

        return EmployeeWorkSchedule::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'effective_from' => now()->subMonth()->toDateString(),
            'is_primary' => true,
        ]);
    }

    private function punch(Employee $employee, string $biometricId, string $time): void
    {
        EmployeeBiometricId::firstOrCreate(['employee_id' => $employee->id, 'biometric_id' => $biometricId]);

        EmployeeAttendanceLog::create([
            'employee_id' => $biometricId,
            'auth_date_time' => now()->startOfMonth()->addDays(2)->setTimeFromTimeString($time),
            'auth_date' => now()->startOfMonth()->addDays(2)->toDateString(),
            'auth_time' => $time,
            'direction' => 'in',
            'device_name' => 'Lobby',
            'device_sn' => 'SN-1',
            'person_name' => $employee->emp_first_name,
            'card_no' => '0',
        ]);
    }
}

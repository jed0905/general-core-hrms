<?php

namespace Tests\Feature\SelfService;

use App\Models\Employee;
use App\Models\EmployeeAttendanceLog;
use App\Models\EmployeeBiometricId;
use App\Models\EmployeeWorkSchedule;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\Feature\Leave\LeaveTestCase;

/**
 * Which dashboard a user gets is decided by dashboard.hr.view, and the
 * self-service dashboard and leave balance only ever show the user's own data.
 */
class EmployeeDashboardTest extends LeaveTestCase
{
    private const EMPLOYEE_DASHBOARD = 'app/Dashboard/MyDashboard/Index';

    private const HR_DASHBOARD = 'app/Dashboard';

    private User $me;

    private User $them;

    protected function setUp(): void
    {
        parent::setUp();

        // A Wednesday, so a Monday–Friday schedule is a working day.
        $this->travelTo(Carbon::parse('2026-10-07 09:30:00'));

        $this->me = $this->userWithRole('employee', ['emp_first_name' => 'Ana', 'emp_last_name' => 'Reyes']);
        $this->them = $this->userWithRole('employee', ['emp_first_name' => 'Ben', 'emp_last_name' => 'Cruz']);
    }

    // ---------------------------------------------------------------
    // Which dashboard
    // ---------------------------------------------------------------

    #[Test]
    public function plain_employee_receives_the_employee_dashboard(): void
    {
        $this->actingAs($this->me)
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component(self::EMPLOYEE_DASHBOARD, false)
                ->where('hasEmployeeRecord', true)
                ->where('employee.name', 'Ana Reyes')
                ->where('employee.employee_number', $this->employeeOf($this->me)->employee_number));
    }

    #[Test]
    public function plain_employee_receives_no_hr_dashboard_data(): void
    {
        foreach (['employee', 'supervisor'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('dashboard.index'))
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->component(self::EMPLOYEE_DASHBOARD, false)
                    ->missing('kpiData')
                    ->missing('employeesPerLocation')
                    ->missing('employeesPerDepartment')
                    ->missing('employeeStatusCounts')
                    ->missing('leaveSummary')
                    ->missing('setupProgress'));
        }
    }

    #[Test]
    public function hr_users_receive_the_hr_dashboard(): void
    {
        foreach (['superadmin', 'hr_director', 'hr_manager', 'hr_staff'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('dashboard.index'))
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->component(self::HR_DASHBOARD, false)
                    ->has('kpiData')
                    ->where('kpiData.totalActiveEmployees', Employee::where('status', 'active')->count()));
        }
    }

    #[Test]
    public function multiple_capabilities_get_the_dashboard_their_permissions_allow(): void
    {
        // employee + HR staff role → HR dashboard
        $hrEmployee = $this->userWithRole('employee');
        $hrEmployee->assignRole('hr_staff');
        $this->actingAs($hrEmployee)->get(route('dashboard.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->component(self::HR_DASHBOARD, false));

        // employee + supervisor (approver, not HR) → employee dashboard
        $supervisor = $this->userWithRole('employee');
        $supervisor->assignRole('supervisor');
        $this->actingAs($supervisor)->get(route('dashboard.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->component(self::EMPLOYEE_DASHBOARD, false));

        // capability granted directly, no HR role at all → HR dashboard
        $this->me->givePermissionTo('dashboard.hr.view');
        $this->actingAs($this->me)->get(route('dashboard.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->component(self::HR_DASHBOARD, false));
    }

    #[Test]
    public function removing_the_hr_dashboard_permission_falls_back_to_the_employee_dashboard(): void
    {
        $hr = $this->userWithRole('hr_staff');
        Role::findByName('hr_staff')->revokePermissionTo('dashboard.hr.view');

        $this->actingAs($hr->fresh())->get(route('dashboard.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component(self::EMPLOYEE_DASHBOARD, false)
                ->missing('kpiData'));
    }

    // ---------------------------------------------------------------
    // Dashboard content
    // ---------------------------------------------------------------

    #[Test]
    public function employee_dashboard_contains_only_the_authenticated_employees_data(): void
    {
        $mine = $this->employeeOf($this->me);
        $theirs = $this->employeeOf($this->them);

        $this->scheduleFor($mine, 'MINE', '08:00', '17:00');
        $this->scheduleFor($theirs, 'THEIRS', '22:00', '06:00');
        $myBalance = $this->balanceFor($mine, balance: 10, pending: 2);
        $this->balanceFor($theirs, balance: 3);
        $myApplication = $this->applicationFor($mine, [], LeaveApplication::STATUS_APPROVED);
        $this->applicationFor($theirs);
        $this->punch($mine, 'BIO-ANA', '08:02:00');
        $this->punch($theirs, 'BIO-BEN', '07:55:00');

        $this->actingAs($this->me)
            // an employee id in the URL is not an authority for anything
            ->get(route('dashboard.index', ['employee_id' => $theirs->id, 'employee' => $theirs->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('employee.name', 'Ana Reyes')
                ->where('today.schedule.code', 'MINE')
                ->where('today.is_working_day', true)
                ->where('today.shift.start_time', fn ($time) => str_starts_with($time, '08:00'))
                ->where('nextWorkingDay.date', '2026-10-08')
                ->has('leaveBalances', 1)
                ->where('leaveBalances.0.id', $myBalance->id)
                ->where('leaveBalances.0.available', 8)
                ->has('recentLeave', 1)
                ->where('recentLeave.0.id', $myApplication->id)
                ->where('attendance.status', 'timed_in')
                ->where('attendance.first_in', '08:02:00')
                ->has('attendance.recent', 1)
                ->where('attendance.recent.0.auth_time', '08:02:00'));
    }

    #[Test]
    public function holidays_and_rest_days_are_reflected_in_today_and_next_working_day(): void
    {
        $mine = $this->employeeOf($this->me);
        $this->scheduleFor($mine, 'MINE', '08:00', '17:00');
        Holiday::create(['name' => 'Founders Day', 'date' => '2026-10-07', 'is_working_day' => false]);
        Holiday::create(['name' => 'Recurring', 'date' => '2020-10-08', 'is_recurring' => true, 'is_working_day' => false]);

        $this->actingAs($this->me)->get(route('dashboard.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('today.is_working_day', false)
                ->where('today.holiday.name', 'Founders Day')
                ->where('attendance.status', 'holiday')
                // Thu is a recurring holiday, Sat/Sun are rest days → Fri
                ->where('nextWorkingDay.date', '2026-10-09'));
    }

    #[Test]
    public function dashboard_sections_follow_self_service_permissions(): void
    {
        $role = Role::create(['name' => 'profile_only', 'guard_name' => 'web'])->givePermissionTo('employee.view_own');
        $user = User::factory()->create(['employee_id' => Employee::factory()->create()->id])->assignRole($role);

        $this->actingAs($user)->get(route('dashboard.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component(self::EMPLOYEE_DASHBOARD, false)
                ->whereNot('employee', null)
                ->where('today', null)
                ->where('nextWorkingDay', null)
                ->where('attendance', null)
                ->where('leaveBalances', null)
                ->where('recentLeave', null));
    }

    #[Test]
    public function account_without_employee_record_gets_an_empty_employee_dashboard(): void
    {
        $user = User::factory()->create()->assignRole('employee');

        $this->actingAs($user)->get(route('dashboard.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component(self::EMPLOYEE_DASHBOARD, false)
                ->where('hasEmployeeRecord', false)
                ->where('employee', null)
                ->where('leaveBalances', null));
    }

    // ---------------------------------------------------------------
    // Leave balance
    // ---------------------------------------------------------------

    #[Test]
    public function employee_can_view_own_leave_balance(): void
    {
        $balance = $this->balanceFor($this->me, balance: 12.5, pending: 2, used: 3);

        $this->actingAs($this->me)
            ->get(route('leave.applications.my-balances'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('app/Leave/MyBalances/Index', false)
                ->has('balances', 1)
                ->where('balances.0.id', $balance->id)
                ->where('balances.0.balance', 12.5)
                ->where('balances.0.pending', 2)
                ->where('balances.0.used', 3)
                ->where('balances.0.available', 10.5)
                ->where('balances.0.leave_type.code', 'VL'));
    }

    #[Test]
    public function employee_cannot_view_another_employees_leave_balance(): void
    {
        $this->balanceFor($this->them, balance: 3);

        $this->actingAs($this->me)->get(route('leave.balances.index'))->assertForbidden();
        $this->assertFalse($this->me->can('viewLeaveBalance', $this->employeeOf($this->them)));
        $this->assertTrue($this->me->can('viewLeaveBalance', $this->employeeOf($this->me)));
    }

    #[Test]
    public function leave_balance_ownership_cannot_be_bypassed_through_url_parameters(): void
    {
        $mine = $this->balanceFor($this->me, balance: 10);
        $this->balanceFor($this->them, balance: 3);
        $theirId = $this->them->employee_id;

        foreach ([['employee_id' => $theirId], ['employee' => $theirId], ['user_id' => $this->them->id]] as $params) {
            $this->actingAs($this->me)
                ->get(route('leave.applications.my-balances', $params))
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->has('balances', 1)
                    ->where('balances.0.id', $mine->id));
        }
    }

    #[Test]
    public function removing_the_balance_permission_blocks_the_page_and_the_dashboard_section(): void
    {
        $this->balanceFor($this->me);
        Role::findByName('employee')->revokePermissionTo('leave.view_balance_own');
        $me = $this->me->fresh();

        $this->actingAs($me)->get(route('leave.applications.my-balances'))->assertForbidden();
        $this->actingAs($me)->get(route('dashboard.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('leaveBalances', null));
    }

    // ---------------------------------------------------------------
    // Existing Leave self-service
    // ---------------------------------------------------------------

    #[Test]
    public function existing_leave_self_service_keeps_working(): void
    {
        $mine = $this->employeeOf($this->me);
        $decided = $this->applicationFor($mine, [], LeaveApplication::STATUS_APPROVED);
        $this->applicationFor($mine); // pending: not history
        $this->applicationFor($this->employeeOf($this->them), [], LeaveApplication::STATUS_REJECTED);

        $this->actingAs($this->me)->get(route('leave.applications.index'))->assertOk();
        $this->actingAs($this->me)->get(route('leave.applications.create'))
            ->assertRedirect(route('leave.applications.index', ['apply' => 1]));

        $this->actingAs($this->me)
            ->get(route('leave.applications.history', ['employee_id' => $this->them->employee_id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('app/Leave/MyHistory/Index', false)
                ->where('history.total', 1)
                ->where('history.data.0.id', $decided->id)
                ->where('history.data.0.can.view', true));

        $this->actingAs($this->me)->get(route('leave.applications.show', $decided))->assertOk();
    }

    #[Test]
    public function self_service_leave_pages_need_their_permissions(): void
    {
        $role = Role::create(['name' => 'no_leave', 'guard_name' => 'web'])->givePermissionTo('employee.view_own');
        $user = User::factory()->create(['employee_id' => Employee::factory()->create()->id])->assignRole($role);

        foreach (['leave.applications.index', 'leave.applications.create', 'leave.applications.my-balances', 'leave.applications.history'] as $name) {
            $this->actingAs($user)->get(route($name))->assertForbidden();
        }
    }

    private function scheduleFor(Employee $employee, string $code, string $start, string $end): void
    {
        $shift = Shift::create(['name' => "{$code} shift", 'code' => "{$code}-S", 'start_time' => $start, 'end_time' => $end, 'required_hours' => 8]);
        $schedule = WorkSchedule::create(['name' => "{$code} schedule", 'code' => $code]);

        foreach (range(0, 6) as $day) {
            $schedule->days()->create([
                'day_of_week' => $day,
                'shift_id' => $shift->id,
                'is_working_day' => $day >= 1 && $day <= 5,
            ]);
        }

        EmployeeWorkSchedule::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'effective_from' => '2026-01-01',
            'is_primary' => true,
        ]);
    }

    private function punch(Employee $employee, string $biometricId, string $time): void
    {
        EmployeeBiometricId::create(['employee_id' => $employee->id, 'biometric_id' => $biometricId]);

        EmployeeAttendanceLog::create([
            'employee_id' => $biometricId,
            'auth_date_time' => '2026-10-07 '.$time,
            'auth_date' => '2026-10-07',
            'auth_time' => $time,
            'direction' => 'in',
            'device_name' => 'Lobby',
            'device_sn' => 'SN-1',
            'person_name' => $employee->emp_first_name,
            'card_no' => '0',
        ]);
    }
}

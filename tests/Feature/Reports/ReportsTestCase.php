<?php

namespace Tests\Feature\Reports;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeAttendanceLog;
use App\Models\EmployeeBiometricId;
use App\Models\EmployeeLeaveBalance;
use App\Models\EmployeeWorkSchedule;
use App\Models\EmploymentStatus;
use App\Models\JobTitle;
use App\Models\LeaveApplication;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Reports\ReportRegistry;
use Database\Seeders\EmployeeMovementTypeSeeder;
use Tests\Feature\Leave\LeaveTestCase;

/**
 * Seeded roles (via the Leave base) plus a small organization to report on.
 */
abstract class ReportsTestCase extends LeaveTestCase
{
    protected Department $it;

    protected Department $dev;

    protected Department $hr;

    protected JobTitle $developer;

    protected JobTitle $recruiter;

    protected EmploymentStatus $regular;

    protected EmploymentStatus $probationary;

    protected Employee $ana;   // IT, developer, regular, female

    protected Employee $ben;   // Dev (child of IT), developer, probationary, male

    protected Employee $cara;  // HR, recruiter, regular, female

    protected Employee $dan;   // IT, archived

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-07 09:00:00');
        $this->seed(EmployeeMovementTypeSeeder::class);

        $this->it = Department::create(['name' => 'Information Technology', 'shortcut' => 'IT']);
        $this->dev = Department::create(['name' => 'Development', 'shortcut' => 'DEV', 'parent_id' => $this->it->id]);
        $this->hr = Department::create(['name' => 'Human Resources', 'shortcut' => 'HR']);
        $this->developer = JobTitle::create(['job_title' => 'Developer']);
        $this->recruiter = JobTitle::create(['job_title' => 'Recruiter']);
        $this->regular = EmploymentStatus::create(['name' => 'Regular']);
        $this->probationary = EmploymentStatus::create(['name' => 'Probationary']);

        $this->ana = $this->person('Ana', 'Reyes', 'female', $this->it, $this->developer, $this->regular, ['emp_birthday' => '1996-03-01', 'joined_date' => '2026-10-01']);
        $this->ben = $this->person('Ben', 'Cruz', 'male', $this->dev, $this->developer, $this->probationary, ['emp_birthday' => '1985-06-15', 'joined_date' => '2025-01-10', 'supervisor_id' => $this->ana->id]);
        $this->cara = $this->person('Cara', 'Diaz', 'female', $this->hr, $this->recruiter, $this->regular, ['joined_date' => '2026-10-05']);
        $this->dan = $this->person('Dan', 'Lim', 'male', $this->it, $this->developer, $this->regular, ['status' => 'archived']);
    }

    protected function person(string $first, string $last, string $sex, Department $department, JobTitle $title, EmploymentStatus $status, array $extra = []): Employee
    {
        return Employee::factory()->create(array_merge([
            'emp_first_name' => $first,
            'emp_last_name' => $last,
            'emp_sex' => $sex,
            'department_id' => $department->id,
            'job_title_id' => $title->id,
            'employment_status_id' => $status->id,
        ], $extra));
    }

    protected function schedule(Employee $employee): void
    {
        $shift = Shift::firstOrCreate(['code' => 'DAY'], ['name' => 'Day', 'start_time' => '08:00', 'end_time' => '17:00', 'required_hours' => 8]);
        $schedule = WorkSchedule::firstOrCreate(['code' => 'M-F'], ['name' => 'Mon–Fri']);

        if (! $schedule->days()->exists()) {
            foreach (range(0, 6) as $day) {
                $schedule->days()->create(['day_of_week' => $day, 'shift_id' => $shift->id, 'is_working_day' => $day >= 1 && $day <= 5]);
            }
        }

        EmployeeWorkSchedule::create(['employee_id' => $employee->id, 'work_schedule_id' => $schedule->id, 'effective_from' => '2026-01-01', 'is_primary' => true]);
    }

    protected function punch(?Employee $employee, string $biometricId, string $date, string $time, string $direction = 'in'): void
    {
        if ($employee) {
            EmployeeBiometricId::firstOrCreate(['biometric_id' => $biometricId], ['employee_id' => $employee->id]);
        }

        EmployeeAttendanceLog::create([
            'employee_id' => $biometricId,
            'auth_date_time' => "{$date} {$time}",
            'auth_date' => $date,
            'auth_time' => $time,
            'direction' => $direction,
            'device_name' => 'Lobby',
            'device_sn' => 'SN-001',
            'person_name' => $employee?->emp_first_name ?? 'Unknown',
            'card_no' => '0',
        ]);
    }

    protected function approvedLeave(Employee $employee, array $dates, string $status = LeaveApplication::STATUS_APPROVED): LeaveApplication
    {
        $application = LeaveApplication::factory()->status($status)->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->leaveType->id,
            'total_days' => array_sum($dates),
            'total_hours' => array_sum($dates) * 8,
            'submitted_at' => '2026-09-01 10:00:00',
        ]);

        foreach ($dates as $date => $fraction) {
            $application->dates()->create(['leave_date' => $date, 'duration_type' => $fraction < 1 ? 'half_day' : 'full_day', 'hours' => $fraction * 8, 'day_fraction' => $fraction]);
        }

        return $application;
    }

    protected function balance(Employee $employee, float $balance, float $pending, float $used): EmployeeLeaveBalance
    {
        return EmployeeLeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->leaveType->id,
            'balance' => $balance,
            'pending' => $pending,
            'used' => $used,
        ]);
    }

    /**
     * An account with a role but no employee record, so it doesn't show up in the counts.
     */
    protected function reportUser(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    /**
     * @return array<int, string> every report key
     */
    protected static function reportKeys(): array
    {
        return array_map(fn ($class) => $class::key(), ReportRegistry::REPORTS);
    }

    protected function showUrl(string $key, array $params = []): string
    {
        return route("reports.{$key}.show", $params);
    }

    /**
     * Rows of a rendered report page.
     */
    protected function rowsOf(User $user, string $key, array $params = []): array
    {
        return $this->actingAs($user)->get($this->showUrl($key, $params))->assertOk()
            ->viewData('page')['props']['rows']['data'];
    }
}

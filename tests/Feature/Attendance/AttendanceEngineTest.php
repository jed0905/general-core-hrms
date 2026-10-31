<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceAdjustment;
use App\Models\AttendancePolicy;
use App\Models\EmployeeShiftAssignment;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\Shift;
use App\Services\Attendance\Engine\AttendanceRecalculator;
use PHPUnit\Framework\Attributes\Test;

/**
 * Daily attendance from raw punches (Monday 2026-10-12 onward). Day shift
 * 08:00-17:00, unpaid break 12:00-13:00, 8 required hours, Asia/Manila.
 * Policy values here are test settings, not legal rules.
 */
class AttendanceEngineTest extends AttendanceTestCase
{
    private function policy(array $values): void
    {
        AttendancePolicy::where('is_default', true)->update($values);
    }

    #[Test]
    public function a_normal_day_deducts_the_scheduled_break(): void
    {
        $this->policy(['overtime_threshold_minutes' => 30]);
        $device = $this->device();
        $employee = $this->scheduledEmployee();
        $this->enroll($device, $employee, '1001');

        $this->punch($device, '1001', ['2026-10-12 07:58' => 'check_in', '2026-10-12 17:02' => 'check_out']);
        $day = $this->day($employee, '2026-10-12');

        $this->assertSame('workday', $day->day_type);
        $this->assertSame('present', $day->status);
        $this->assertSame(484, $day->worked_minutes);
        $this->assertSame(0, $day->late_minutes);
        $this->assertSame(0, $day->undertime_minutes);
        $this->assertSame(0, $day->overtime_minutes); // 2 minutes is under the 30-minute threshold
        $this->assertSame(480, $day->scheduled_minutes);
        $this->assertSame('2026-10-11T23:58:00+00:00', $day->first_in->toIso8601String()); // stored as UTC
    }

    #[Test]
    public function lateness_respects_the_grace_period_and_early_departure_is_undertime(): void
    {
        $this->policy(['late_grace_minutes' => 10, 'undertime_grace_minutes' => 0]);
        $device = $this->device();
        $employee = $this->scheduledEmployee();
        $this->enroll($device, $employee, '7');

        $this->punch($device, '7', [
            '2026-10-12 08:05' => 'check_in', '2026-10-12 17:00' => 'check_out', // within grace
            '2026-10-13 08:20' => 'check_in', '2026-10-13 16:30' => 'check_out', // late and early
        ]);

        $this->assertSame(0, $this->day($employee, '2026-10-12')->late_minutes);
        $late = $this->day($employee, '2026-10-13');
        $this->assertSame(20, $late->late_minutes);
        $this->assertSame(30, $late->undertime_minutes);
        $this->assertSame('late', $late->status);
    }

    #[Test]
    public function a_missing_punch_makes_the_day_incomplete(): void
    {
        $device = $this->device();
        $employee = $this->scheduledEmployee();
        $this->enroll($device, $employee, '7');

        $this->punch($device, '7', ['2026-10-12 08:00' => 'check_in']);
        $day = $this->day($employee, '2026-10-12');

        $this->assertSame('incomplete', $day->status);
        $this->assertSame('missing_out', $day->exceptions[0]['code']);
        $this->assertSame(0, $day->worked_minutes);
    }

    #[Test]
    public function multiple_in_out_pairs_are_summed_and_break_punches_replace_the_scheduled_break(): void
    {
        $device = $this->device();
        $employee = $this->scheduledEmployee();
        $this->enroll($device, $employee, '7');

        $this->punch($device, '7', [
            '2026-10-12 08:00' => 'check_in', '2026-10-12 11:30' => 'break_out',
            '2026-10-12 12:00' => 'break_in', '2026-10-12 17:00' => 'check_out',
        ]);

        $day = $this->day($employee, '2026-10-12');
        $this->assertSame(510, $day->worked_minutes); // 3h30 + 5h, no scheduled break deducted
        $this->assertCount(2, $day->pairs);
    }

    #[Test]
    public function first_last_pairing_ignores_the_punches_in_between(): void
    {
        $this->policy(['pairing_mode' => 'first_last']);
        $device = $this->device();
        $employee = $this->scheduledEmployee();
        $this->enroll($device, $employee, '7');

        $this->punch($device, '7', ['2026-10-12 08:00' => 'unknown', '2026-10-12 10:00' => 'unknown', '2026-10-12 10:05' => 'unknown', '2026-10-12 17:00' => 'unknown']);

        $this->assertSame(480, $this->day($employee, '2026-10-12')->worked_minutes);
    }

    #[Test]
    public function an_overnight_shift_belongs_to_its_start_date_and_counts_night_time(): void
    {
        $this->policy(['night_start' => '22:00:00', 'night_end' => '06:00:00', 'overtime_threshold_minutes' => 30]);
        $night = Shift::create(['name' => 'Night', 'code' => 'NIGHT', 'start_time' => '22:00:00', 'end_time' => '06:00:00', 'required_hours' => 8, 'is_overnight' => true, 'is_active' => true]);
        $device = $this->device();
        $employee = $this->scheduledEmployee($night);
        $this->enroll($device, $employee, '9');

        $this->punch($device, '9', ['2026-10-12 21:55' => 'check_in', '2026-10-13 06:05' => 'check_out']);

        $monday = $this->day($employee, '2026-10-12');
        $this->assertSame(490, $monday->worked_minutes);
        $this->assertSame(480, $monday->night_minutes);
        $this->assertSame('present', $monday->status);
        $this->assertSame('absent', $this->day($employee, '2026-10-13')->status); // Tuesday's 22:00 shift has no punches
    }

    #[Test]
    public function a_roster_entry_overrides_the_weekly_schedule_for_rotating_shifts(): void
    {
        $evening = Shift::create(['name' => 'Evening', 'code' => 'EVE', 'start_time' => '14:00:00', 'end_time' => '22:00:00', 'required_hours' => 8, 'is_active' => true]);
        $device = $this->device();
        $employee = $this->scheduledEmployee();
        $this->enroll($device, $employee, '7');
        EmployeeShiftAssignment::create(['employee_id' => $employee->id, 'work_date' => '2026-10-13', 'shift_id' => $evening->id]);
        EmployeeShiftAssignment::create(['employee_id' => $employee->id, 'work_date' => '2026-10-14', 'is_rest_day' => true]);

        $this->punch($device, '7', ['2026-10-13 14:10' => 'check_in', '2026-10-13 22:00' => 'check_out']);
        app(AttendanceRecalculator::class)->recalculateRange([$employee->id], '2026-10-14', '2026-10-14');

        $tuesday = $this->day($employee, '2026-10-13');
        $this->assertSame($evening->id, $tuesday->shift_id);
        $this->assertSame(10, $tuesday->late_minutes);
        $this->assertSame('rest_day', $this->day($employee, '2026-10-14')->status);
    }

    #[Test]
    public function rest_day_work_is_bucketed_as_rest_day_time(): void
    {
        $device = $this->device();
        $employee = $this->scheduledEmployee();
        $this->enroll($device, $employee, '7');

        $this->punch($device, '7', ['2026-10-17 09:00' => 'check_in', '2026-10-17 15:00' => 'check_out']); // Saturday

        $day = $this->day($employee, '2026-10-17');
        $this->assertSame('rest_day', $day->day_type);
        $this->assertSame(360, $day->worked_minutes);
        $this->assertSame(['rest_day||0|0' => 360], $day->buckets);
    }

    #[Test]
    public function only_holidays_of_the_employees_jurisdiction_apply(): void
    {
        Holiday::create(['name' => 'Everywhere day', 'date' => '2026-10-13', 'type' => 'regular', 'is_working_day' => false, 'status' => 'active']);
        Holiday::create(['name' => 'Foreign day', 'date' => '2026-10-15', 'type' => 'regular', 'country_code' => 'SG', 'is_working_day' => false, 'status' => 'active']);
        $device = $this->device();
        $employee = $this->scheduledEmployee();
        $this->enroll($device, $employee, '7');

        $this->punch($device, '7', ['2026-10-13 08:00' => 'check_in', '2026-10-13 12:00' => 'check_out']);
        app(AttendanceRecalculator::class)->recalculateRange([$employee->id], '2026-10-15', '2026-10-15');

        $holiday = $this->day($employee, '2026-10-13');
        $this->assertSame('holiday', $holiday->day_type);
        $this->assertSame('regular', $holiday->holiday_type);
        $this->assertSame(['holiday|regular|0|0' => 240], $holiday->buckets);
        $this->assertSame('absent', $this->day($employee, '2026-10-15')->status); // SG holiday doesn't apply
    }

    #[Test]
    public function an_unworked_workday_is_an_absence_unless_approved_leave_covers_it(): void
    {
        $employee = $this->scheduledEmployee();
        $leave = LeaveApplication::factory()->status(LeaveApplication::STATUS_APPROVED)->create(['employee_id' => $employee->id, 'leave_type_id' => $this->leaveType->id]);
        $leave->dates()->create(['leave_date' => '2026-10-13', 'duration_type' => 'full_day', 'is_paid' => true]);
        $pending = LeaveApplication::factory()->status(LeaveApplication::STATUS_PENDING)->create(['employee_id' => $employee->id, 'leave_type_id' => $this->leaveType->id]);
        $pending->dates()->create(['leave_date' => '2026-10-14', 'duration_type' => 'full_day', 'is_paid' => true]);

        app(AttendanceRecalculator::class)->recalculateRange([$employee->id], '2026-10-12', '2026-10-14');

        $this->assertSame('absent', $this->day($employee, '2026-10-12')->status);
        $this->assertSame('1.0000', $this->day($employee, '2026-10-12')->absence_days);
        $this->assertSame('on_leave', $this->day($employee, '2026-10-13')->status);
        $this->assertSame('0.0000', $this->day($employee, '2026-10-13')->absence_days);
        $this->assertSame('absent', $this->day($employee, '2026-10-14')->status); // pending leave doesn't count
    }

    #[Test]
    public function approved_partial_leave_covers_the_missing_half_day(): void
    {
        $device = $this->device();
        $employee = $this->scheduledEmployee();
        $this->enroll($device, $employee, '7');
        $leave = LeaveApplication::factory()->status(LeaveApplication::STATUS_APPROVED)->create(['employee_id' => $employee->id, 'leave_type_id' => $this->leaveType->id]);
        $leave->dates()->create(['leave_date' => '2026-10-12', 'duration_type' => 'half_day', 'day_fraction' => '0.5', 'is_paid' => false]);

        $this->punch($device, '7', ['2026-10-12 08:00' => 'check_in', '2026-10-12 12:00' => 'check_out']);

        $day = $this->day($employee, '2026-10-12');
        $this->assertSame(0, $day->undertime_minutes); // 4 scheduled working hours missed (break excluded), all covered by the half-day leave
        $this->assertSame('0.5000', $day->leave_days);
    }

    #[Test]
    public function overtime_needing_approval_is_paid_only_once_approved(): void
    {
        $this->policy(['overtime_requires_approval' => true, 'overtime_threshold_minutes' => 30]);
        $device = $this->device();
        $employee = $this->scheduledEmployee();
        $this->enroll($device, $employee, '7');

        $this->punch($device, '7', ['2026-10-12 08:00' => 'check_in', '2026-10-12 19:00' => 'check_out']);
        $day = $this->day($employee, '2026-10-12');
        $this->assertSame(120, $day->overtime_minutes);
        $this->assertSame(0, $day->approved_overtime_minutes);
        $this->assertSame(['workday||0|0' => 480], $day->buckets);

        AttendanceAdjustment::create(['employee_id' => $employee->id, 'work_date' => '2026-10-12', 'type' => 'approve_overtime', 'corrected' => ['minutes' => 90], 'reason' => 'Month-end close', 'status' => 'approved']);
        app(AttendanceRecalculator::class)->recalculateRange([$employee->id], '2026-10-12', '2026-10-12');

        $day = $this->day($employee, '2026-10-12');
        $this->assertSame(90, $day->approved_overtime_minutes);
        $this->assertSame(['workday||0|0' => 480, 'workday||1|0' => 90], $day->buckets);
    }

    #[Test]
    public function a_night_across_a_daylight_saving_change_counts_real_elapsed_time(): void
    {
        AttendancePolicy::where('is_default', true)->update(['timezone' => 'America/New_York']);
        $night = Shift::create(['name' => 'Night', 'code' => 'N2', 'start_time' => '22:00:00', 'end_time' => '06:00:00', 'required_hours' => 8, 'is_overnight' => true, 'is_active' => true]);
        $device = $this->device(['timezone' => 'America/New_York']);
        $employee = $this->scheduledEmployee($night, [0, 1, 2, 3, 4, 5, 6]);
        $this->enroll($device, $employee, '5');

        // Clocks fall back at 02:00 on Sunday 2026-11-01: the night lasts 9 real hours.
        $this->punch($device, '5', ['2026-10-31 22:00' => 'check_in', '2026-11-01 06:00' => 'check_out']);

        $this->assertSame(540, $this->day($employee, '2026-10-31')->worked_minutes);
    }

    #[Test]
    public function device_and_employee_timezones_may_differ(): void
    {
        $device = $this->device(['timezone' => 'UTC']);
        $employee = $this->scheduledEmployee();
        $this->enroll($device, $employee, '7');

        // 00:05 UTC is 08:05 in Manila, the employee's (policy) timezone.
        $this->punch($device, '7', ['2026-10-12 00:05' => 'check_in', '2026-10-12 09:00' => 'check_out']);

        $day = $this->day($employee, '2026-10-12');
        $this->assertSame(5, $day->late_minutes);
        $this->assertSame('Asia/Manila', $day->timezone);
    }
}

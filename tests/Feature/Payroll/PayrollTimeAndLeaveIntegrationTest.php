<?php

namespace Tests\Feature\Payroll;

use App\Models\EmployeeWorkSchedule;
use App\Models\EmploymentAssignment;
use App\Models\LeaveApplication;
use App\Models\Shift;
use App\Models\WorkSchedule;
use PHPUnit\Framework\Attributes\Test;

/**
 * Payroll reads leave and schedules from their own modules and never recomputes
 * them: only APPROVED leave counts (is_paid on each date decides paid/unpaid),
 * and "working_days" proration uses the Time module's schedule resolution.
 * Statutory values are FICTIONAL (country XX).
 */
class PayrollTimeAndLeaveIntegrationTest extends PayrollTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->standardRules();
    }

    private function leave(EmploymentAssignment $assignment, string $status, array $dates): LeaveApplication
    {
        $application = LeaveApplication::factory()->status($status)->create([
            'employee_id' => $assignment->employee_id,
            'leave_type_id' => $this->leaveType->id,
            'total_days' => count($dates),
        ]);

        foreach ($dates as $date => $attributes) {
            $application->dates()->create(array_merge(['leave_date' => $date, 'duration_type' => 'full_day', 'is_paid' => false], $attributes));
        }

        return $application;
    }

    #[Test]
    public function approved_unpaid_leave_is_deducted_as_leave_without_pay(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $assignment = $this->employeeOn($this->payGroup, 'monthly', '3000.00');
        $this->leave($assignment, LeaveApplication::STATUS_APPROVED, [
            '2026-10-05' => [],
            '2026-10-06' => ['duration_type' => 'half_day', 'day_fraction' => '0.5'],
            '2026-10-07' => ['is_paid' => true], // paid leave: salary continues
        ]);

        $run = $this->calculate($this->runFor($period));
        $lwop = $run->lines()->where('code', 'LWOP')->first();

        // 1.5 days x (3000 x 12 / 260)
        $this->assertSame('-207.6900', $lwop->amount);
        $this->assertSame('1.5000', $lwop->quantity);
        $this->assertSame(['2026-10-05', '2026-10-06'], array_column($lwop->inputs['leave_dates'], 'date'));
    }

    #[Test]
    public function pending_rejected_returned_and_cancelled_leave_is_ignored(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $assignment = $this->employeeOn($this->payGroup, 'monthly', '3000.00');
        $this->leave($assignment, LeaveApplication::STATUS_PENDING, ['2026-10-05' => []]);
        $this->leave($assignment, LeaveApplication::STATUS_REJECTED, ['2026-10-06' => []]);
        $this->leave($assignment, LeaveApplication::STATUS_RETURNED, ['2026-10-07' => []]);
        $this->leave($assignment, LeaveApplication::STATUS_CANCELLED, ['2026-10-08' => []]);

        $lines = $this->linesOf($this->calculate($this->runFor($period)), $assignment);

        $this->assertArrayNotHasKey('LWOP', $lines);
        $this->assertSame('3000.0000', $lines['BASIC']);
    }

    #[Test]
    public function leave_outside_the_period_is_ignored(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $assignment = $this->employeeOn($this->payGroup, 'monthly', '3000.00');
        $this->leave($assignment, LeaveApplication::STATUS_APPROVED, ['2026-09-30' => [], '2026-11-01' => []]);

        $this->assertArrayNotHasKey('LWOP', $this->linesOf($this->calculate($this->runFor($period)), $assignment));
    }

    #[Test]
    public function a_daily_rated_employee_is_paid_for_approved_paid_leave(): void
    {
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $assignment = $this->employeeOn($this->payGroup, 'daily', '100.00');
        $this->timeInput($period, $assignment, 'days_worked', '20');
        $this->leave($assignment, LeaveApplication::STATUS_APPROVED, ['2026-10-12' => ['is_paid' => true], '2026-10-13' => ['is_paid' => false]]);

        $lines = $this->linesOf($this->calculate($this->runFor($period)), $assignment);

        $this->assertSame('2000.0000', $lines['BASIC']);
        $this->assertSame('100.0000', $lines['PAID_LEAVE']);
        $this->assertArrayNotHasKey('LWOP', $lines); // unpaid days are simply not worked
    }

    #[Test]
    public function working_day_proration_uses_the_employee_schedule(): void
    {
        $this->payGroup->update(['proration_method' => 'working_days']);
        $period = $this->period($this->payGroup, '2026-10-01', '2026-10-31');
        $assignment = $this->employeeOn($this->payGroup, 'monthly', '2200.00', from: '2026-10-19');

        $shift = Shift::create(['name' => 'Day', 'code' => 'DAY', 'start_time' => '09:00', 'end_time' => '17:00', 'required_hours' => 8]);
        $schedule = WorkSchedule::create(['name' => 'Mon-Fri', 'code' => 'MF', 'status' => 'active']);
        foreach (range(0, 6) as $day) {
            $schedule->days()->create(['day_of_week' => $day, 'shift_id' => $shift->id, 'is_working_day' => $day >= 1 && $day <= 5]);
        }
        EmployeeWorkSchedule::create(['employee_id' => $assignment->employee_id, 'work_schedule_id' => $schedule->id, 'effective_from' => '2026-01-01', 'is_primary' => true]);

        $basic = $this->linesOf($this->calculate($this->runFor($period)), $assignment)['BASIC'];

        // October 2026 has 22 weekdays; from Monday 19 October there are 10.
        $this->assertSame('1000.0000', $basic);
    }
}

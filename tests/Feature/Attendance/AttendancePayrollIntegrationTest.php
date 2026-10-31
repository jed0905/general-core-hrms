<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceAdjustment;
use App\Models\AttendanceDevice;
use App\Models\AttendancePeriodSummary;
use App\Models\AttendancePolicy;
use App\Models\BiometricEnrollment;
use App\Models\EmployeeWorkSchedule;
use App\Models\Shift;
use App\Models\WorkSchedule;
use App\Services\Attendance\AttendanceAdjustmentService;
use App\Services\Attendance\AttendancePeriodService;
use App\Services\Attendance\Devices\Support\NormalizedEvent;
use App\Services\Attendance\Engine\AttendanceRecalculator;
use App\Services\Attendance\Ingestion\EventIngestionService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Payroll\PayrollTestCase;

/**
 * Attendance is payroll's time source when the pay group says so: payroll
 * reads only APPROVED attendance summaries, never recalculates attendance, and
 * a finalized payroll never changes when attendance is corrected afterwards.
 * Payroll country XX and its rules are FICTIONAL.
 */
class AttendancePayrollIntegrationTest extends PayrollTestCase
{
    private AttendanceDevice $device;

    protected function setUp(): void
    {
        parent::setUp();
        $this->standardRules();
        AttendancePolicy::where('is_default', true)->update(['timezone' => 'UTC', 'overtime_requires_approval' => false, 'overtime_threshold_minutes' => 30]);
        $this->payGroup->update(['time_source' => 'attendance']);
        $this->entity->update(['timezone' => 'UTC']);
        $this->device = AttendanceDevice::create(['name' => 'Sim', 'vendor' => 'simulator', 'serial_number' => 'S1', 'timezone' => 'UTC', 'connection_method' => 'simulator']);
    }

    private function attendanceFor(int $employeeId): void
    {
        $shift = Shift::create(['name' => 'Day', 'code' => 'D'.$employeeId, 'start_time' => '08:00:00', 'end_time' => '16:00:00', 'required_hours' => 8, 'is_active' => true]);
        $schedule = WorkSchedule::create(['name' => 'MF'.$employeeId, 'code' => 'MF'.$employeeId, 'status' => 'active']);
        foreach (range(0, 6) as $day) {
            $schedule->days()->create(['day_of_week' => $day, 'shift_id' => $shift->id, 'is_working_day' => $day >= 1 && $day <= 5]);
        }
        EmployeeWorkSchedule::create(['employee_id' => $employeeId, 'work_schedule_id' => $schedule->id, 'effective_from' => '2026-01-01', 'is_primary' => true]);
        BiometricEnrollment::create(['attendance_device_id' => $this->device->id, 'employee_id' => $employeeId, 'device_user_id' => (string) $employeeId, 'status' => 'active', 'enrolled_on' => '2026-01-01']);

        // Mon 12: 2h overtime; Tue 13: absent; Wed-Fri: normal.
        $events = [];
        foreach (['2026-10-12' => '18:00', '2026-10-14' => '16:00', '2026-10-15' => '16:00', '2026-10-16' => '16:00'] as $date => $out) {
            $events[] = new NormalizedEvent((string) $employeeId, null, "{$date} 08:00", CarbonImmutable::parse("{$date} 08:00", 'UTC'), 'check_in');
            $events[] = new NormalizedEvent((string) $employeeId, null, "{$date} {$out}", CarbonImmutable::parse("{$date} {$out}", 'UTC'), 'check_out');
        }
        app(EventIngestionService::class)->ingest($this->device, $events, 'simulator');
        app(AttendanceRecalculator::class)->recalculateRange([$employeeId], '2026-10-12', '2026-10-16');
    }

    #[Test]
    public function approved_attendance_quantities_drive_payroll_and_history_stays_fixed(): void
    {
        $period = $this->period($this->payGroup, '2026-10-12', '2026-10-16', '2026-10-16');
        $assignment = $this->employeeOn($this->payGroup, 'monthly', '3000.00');
        $this->attendanceFor($assignment->employee_id);

        // Without an approved summary payroll refuses to guess.
        $run = $this->calculate($this->runFor($period));
        $this->assertTrue($run->exceptions()->where('code', 'TIME_INPUTS_NOT_READY')->exists());

        $summary = app(AttendancePeriodService::class)->generate($period, $this->preparer)->first();
        $this->assertSame(['absence_days' => '1.0000', 'days_worked' => '4', 'hours_worked' => '32.0000', 'overtime' => '2.0000'], collect($summary->quantities)->sortKeys()->all());
        app(AttendancePeriodService::class)->approve([$summary->id], $this->director);

        $run = $this->calculate($run->fresh());
        $lines = $this->linesOf($run, $assignment);
        $this->assertSame('-138.4600', $lines['ABSENCE']);          // 1 day x 3000 x 12 / 260
        $this->assertSame('51.9200', $lines['PREMIUM_OVERTIME']); // 2h x 17.3077 x 1.5 (fictional multiplier)
        $this->assertFalse($run->hasBlockingExceptions());

        $run = $this->finalize($run);
        $this->assertSame($run->id, $summary->fresh()->consumed_run_id);

        // A correction after payroll: Tuesday is excused. Attendance changes, payroll history doesn't.
        $hr = $this->userWithRole('hr_manager');
        $adjustment = app(AttendanceAdjustmentService::class)->request($assignment->employee, ['work_date' => '2026-10-13', 'type' => 'mark_excused', 'reason' => 'Official business'], $hr);
        app(AttendanceAdjustmentService::class)->decide($adjustment, true, $this->director, null);

        $summary->refresh();
        $this->assertTrue($summary->changed_after_payroll);
        $this->assertSame('1.0000', $summary->quantities['absence_days']); // what payroll used, unchanged
        $this->assertSame('-138.4600', $this->linesOf($run->fresh(), $assignment)['ABSENCE']);
        $this->assertSame('approved', AttendanceAdjustment::sole()->status);
        $this->assertSame('1.0000', $run->payslips()->first()->snapshot['time_inputs']['absence_days']);
    }

    #[Test]
    public function an_approved_manual_payroll_input_overrides_the_attendance_quantity(): void
    {
        $period = $this->period($this->payGroup, '2026-10-12', '2026-10-16', '2026-10-16');
        $assignment = $this->employeeOn($this->payGroup, 'monthly', '3000.00');
        $this->attendanceFor($assignment->employee_id);
        $summary = app(AttendancePeriodService::class)->generate($period, $this->preparer)->first();
        app(AttendancePeriodService::class)->approve([$summary->id], $this->director);
        $this->timeInput($period, $assignment, 'absence_days', '0');

        $lines = $this->linesOf($this->calculate($this->runFor($period)), $assignment);

        $this->assertArrayNotHasKey('ABSENCE', $lines);
        $this->assertSame('51.9200', $lines['PREMIUM_OVERTIME']);
    }

    #[Test]
    public function changed_attendance_resets_an_unused_approved_summary_to_draft(): void
    {
        $period = $this->period($this->payGroup, '2026-10-12', '2026-10-16', '2026-10-16');
        $assignment = $this->employeeOn($this->payGroup, 'monthly', '3000.00');
        $this->attendanceFor($assignment->employee_id);
        $service = app(AttendancePeriodService::class);
        $summary = $service->generate($period, $this->preparer)->first();
        $service->approve([$summary->id], $this->director);

        $service->generate($period, $this->preparer); // nothing changed
        $this->assertSame(AttendancePeriodSummary::STATUS_APPROVED, $summary->fresh()->status);

        app(EventIngestionService::class)->ingest($this->device, [
            new NormalizedEvent((string) $assignment->employee_id, null, '2026-10-13 08:00', CarbonImmutable::parse('2026-10-13 08:00', 'UTC'), 'check_in'),
            new NormalizedEvent((string) $assignment->employee_id, null, '2026-10-13 16:00', CarbonImmutable::parse('2026-10-13 16:00', 'UTC'), 'check_out'),
        ], 'simulator');
        $service->generate($period, $this->preparer);

        $this->assertSame(AttendancePeriodSummary::STATUS_DRAFT, $summary->fresh()->status);
        $this->assertArrayNotHasKey('absence_days', $summary->fresh()->quantities);
    }

    #[Test]
    public function a_manual_inputs_pay_group_ignores_attendance_entirely(): void
    {
        $this->payGroup->update(['time_source' => 'manual_inputs']);
        $period = $this->period($this->payGroup, '2026-10-12', '2026-10-16', '2026-10-16');
        $assignment = $this->employeeOn($this->payGroup, 'monthly', '3000.00');
        $this->attendanceFor($assignment->employee_id); // an absence and overtime exist in attendance

        $run = $this->calculate($this->runFor($period));
        $lines = $this->linesOf($run, $assignment);

        $this->assertFalse($run->exceptions()->where('code', 'TIME_INPUTS_NOT_READY')->exists());
        $this->assertArrayNotHasKey('ABSENCE', $lines);
        $this->assertArrayNotHasKey('PREMIUM_OVERTIME', $lines);
        $this->assertSame(0, AttendancePeriodSummary::count());
    }
}

<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceDay;
use App\Models\AttendanceDevice;
use App\Models\AttendancePolicy;
use App\Models\BiometricEnrollment;
use App\Models\Employee;
use App\Models\EmployeeWorkSchedule;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Attendance\Devices\Support\NormalizedEvent;
use App\Services\Attendance\DeviceSyncService;
use App\Services\Attendance\Ingestion\EventIngestionService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Tests\Feature\Leave\LeaveTestCase;

/**
 * Attendance fixture (no hardware: simulator devices and normalized events).
 * Time is frozen on Wednesday 2026-10-14 12:00 in the app timezone. Devices
 * and the default policy use Asia/Manila (UTC+8, no DST) unless a test says
 * otherwise; the day shift is 08:00-17:00 with a 12:00-13:00 break and 8
 * required hours, Monday to Friday. Inherits the in-memory SQLite guard.
 */
abstract class AttendanceTestCase extends LeaveTestCase
{
    protected User $hr;

    protected Shift $dayShift;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-14 12:00:00');
        config(['attendance.sync.retry_backoff_ms' => 0]);

        $this->hr = $this->userWithRole('hr_manager');
        $this->dayShift = Shift::create(['name' => 'Day', 'code' => 'DAY', 'start_time' => '08:00:00', 'end_time' => '17:00:00', 'break_start' => '12:00:00', 'break_end' => '13:00:00', 'required_hours' => 8, 'is_overnight' => false, 'is_active' => true]);
        AttendancePolicy::where('is_default', true)->update(['timezone' => 'Asia/Manila', 'overtime_requires_approval' => false]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function device(array $attributes = []): AttendanceDevice
    {
        return AttendanceDevice::create(array_merge([
            'name' => 'Simulator '.AttendanceDevice::count(),
            'vendor' => 'simulator',
            'serial_number' => 'SIM-'.AttendanceDevice::count(),
            'timezone' => 'Asia/Manila',
            'connection_method' => 'simulator',
            'simulation' => [],
        ], $attributes));
    }

    protected function scheduledEmployee(?Shift $shift = null, array $workingDays = [1, 2, 3, 4, 5]): Employee
    {
        $shift ??= $this->dayShift;
        $schedule = WorkSchedule::create(['name' => 'Schedule '.$shift->code.WorkSchedule::count(), 'code' => 'WS'.WorkSchedule::count(), 'status' => 'active']);
        foreach (range(0, 6) as $day) {
            $schedule->days()->create(['day_of_week' => $day, 'shift_id' => $shift->id, 'is_working_day' => in_array($day, $workingDays, true)]);
        }
        $employee = Employee::factory()->create();
        EmployeeWorkSchedule::create(['employee_id' => $employee->id, 'work_schedule_id' => $schedule->id, 'effective_from' => '2026-01-01', 'is_primary' => true]);

        return $employee;
    }

    protected function enroll(AttendanceDevice $device, Employee $employee, string $deviceUserId, array $attributes = []): BiometricEnrollment
    {
        return BiometricEnrollment::create(array_merge([
            'attendance_device_id' => $device->id,
            'employee_id' => $employee->id,
            'device_user_id' => $deviceUserId,
            'status' => 'active',
            'enrolled_on' => '2026-01-01',
        ], $attributes));
    }

    /** Punches given as device-local "Y-m-d H:i" => type, ingested through the shared pipeline. */
    protected function punch(AttendanceDevice $device, string $deviceUserId, array $punches): void
    {
        $events = [];
        foreach ($punches as $time => $type) {
            $events[] = new NormalizedEvent($deviceUserId, null, $time, CarbonImmutable::parse($time, $device->timezone)->utc(), $type);
        }
        app(EventIngestionService::class)->ingest($device, $events, 'simulator');
    }

    protected function day(Employee $employee, string $date): AttendanceDay
    {
        return AttendanceDay::where('employee_id', $employee->id)->whereDate('work_date', $date)->firstOrFail();
    }

    protected function sync(AttendanceDevice $device, string $from, string $to)
    {
        return app(DeviceSyncService::class)->sync($device, 'manual', $this->hr, CarbonImmutable::parse($from, 'UTC'), CarbonImmutable::parse($to, 'UTC'));
    }
}

<?php

namespace Tests\Concerns;

use App\Models\AttendanceDevice;
use App\Models\BiometricEnrollment;
use App\Models\Employee;
use App\Services\Attendance\Devices\Support\EventTime;
use App\Services\Attendance\Devices\Support\NormalizedEvent;
use App\Services\Attendance\Ingestion\EventIngestionService;

/**
 * Records punches the way production does: a device, an employee mapping and
 * the attendance ingestion pipeline (attendance_events). A null employee
 * leaves the device user unmapped.
 */
trait RecordsAttendanceEvents
{
    private ?AttendanceDevice $punchDevice = null;

    protected function recordPunch(?Employee $employee, string $deviceUserId, string $localDateTime, string $type = 'check_in'): void
    {
        $device = $this->punchDevice ??= AttendanceDevice::create([
            'name' => 'Lobby', 'vendor' => 'simulator', 'serial_number' => 'SN-TEST', 'timezone' => config('app.timezone'),
            'connection_method' => 'simulator', 'is_active' => false,
        ]);

        if ($employee) {
            BiometricEnrollment::firstOrCreate(
                ['attendance_device_id' => $device->id, 'device_user_id' => $deviceUserId],
                ['employee_id' => $employee->id, 'enrolled_on' => '2026-01-01'],
            );
        }

        app(EventIngestionService::class)->ingest($device, [
            new NormalizedEvent($deviceUserId, null, $localDateTime, EventTime::toUtc($localDateTime, $device->timezone), $type),
        ], 'device_push');
    }
}

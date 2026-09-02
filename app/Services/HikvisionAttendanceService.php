<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeAttendanceLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Shaykhnazar\HikvisionIsapi\Facades\HikvisionIsapi;

class HikvisionAttendanceService
{
    /**
     * Synchronize attendance events from the Hikvision device.
     */
    public function sync(
        ?Carbon $start = null,
        ?Carbon $end = null
    ): array {
        $start ??= now()->subMinutes(5);
        $end ??= now()->addSeconds(10);

        $response = HikvisionIsapi::post(
            '/ISAPI/AccessControl/AcsEvent',
            [
                'AcsEventCond' => [
                    'searchID' => Str::uuid()->toString(),

                    'searchResultPosition' => 0,

                    'maxResults' => 30,

                    'major' => 5,

                    'minor' => 38,

                    'startTime' => $start->toIso8601String(),

                    'endTime' => $end->toIso8601String(),

                    'timeReverseOrder' => true,
                ],
            ]
        );

        $events = data_get(
            $response,
            'AcsEvent.InfoList',
            []
        );

        // Normalize a single event into an array.
        if (
            is_array($events) &&
            isset($events['serialNo'])
        ) {
            $events = [$events];
        }

        $result = [
            'found' => count($events),
            'imported' => 0,
            'skipped' => 0,
            'unmatched' => 0,
            'errors' => [],
        ];

        foreach ($events as $event) {

            try {

                $imported = $this->processEvent($event);

                if ($imported === true) {
                    $result['imported']++;
                } else {
                    $result['skipped']++;
                }
            } catch (\Throwable $e) {

                $result['errors'][] = [
                    'serialNo' => $event['serialNo'] ?? null,
                    'message' => $e->getMessage(),
                ];

                Log::error('Hikvision attendance import failed', [
                    'event' => $event,
                    'exception' => $e,
                ]);
            }
        }

        return $result;
    }

    /**
     * Process one Hikvision attendance event.
     */
    protected function processEvent(array $event): bool
    {
        /*
        |--------------------------------------------------------------------------
        | We only process events with an attendance status.
        |--------------------------------------------------------------------------
        */

        $attendanceStatus = $event['attendanceStatus'] ?? null;

        if (!$attendanceStatus) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Convert Hikvision attendance status to HRMS direction.
        |--------------------------------------------------------------------------
        */

        $direction = match ($attendanceStatus) {
            'checkIn' => 'IN',
            'checkOut' => 'OUT',

            'breakIn' => 'BREAK_IN',
            'breakOut' => 'BREAK_OUT',

            'overtimeIn' => 'OVERTIME_IN',
            'overtimeOut' => 'OVERTIME_OUT',

            default => null,
        };

        // Ignore unknown attendance statuses.
        if (!$direction) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Get the device employee number.
        |--------------------------------------------------------------------------
        */

        $employeeNo = $event['employeeNoString'] ?? null;

        if (!$employeeNo) {
            Log::warning('Hikvision event has no employee number', [
                'event' => $event,
            ]);

            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Find the employee in HRMS.
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        | Replace employee_no below if your Employee model uses
        | a different column for the biometric/device employee ID.
        |
        */

        $employee = Employee::where(
            'employee_number',
            '220464'
        )->first();

        if (!$employee) {

            Log::warning('Hikvision employee not found in HRMS', [
                'employee_no' => $employeeNo,
                'name' => $event['name'] ?? null,
            ]);

            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Parse device timestamp.
        |--------------------------------------------------------------------------
        */

        $authDateTime = Carbon::parse(
            $event['time']
        )->setTimezone(
            config('app.timezone', 'Asia/Manila')
        );

        /*
        |--------------------------------------------------------------------------
        | Device information.
        |--------------------------------------------------------------------------
        */

        $deviceName = $event['deviceName']
            ?? 'Hikvision';

        $deviceSerial = config(
            'hikvision.devices.primary.serial_number'
        );

        $personName = $event['name']
            ?? $employee->name
            ?? '';

        $cardNo = $event['cardNo']
            ?? null;

        /*
        |--------------------------------------------------------------------------
        | Prevent duplicate attendance records.
        |--------------------------------------------------------------------------
        |
        | We use the actual attendance data available in your current
        | employee_attendance_logs table.
        |
        */

        $exists = EmployeeAttendanceLog::where(
            'employee_id',
            $employee->id
        )
            ->where(
                'auth_date_time',
                $authDateTime
            )
            ->where(
                'direction',
                $direction
            )
            ->where(
                'device_sn',
                $deviceSerial
            )
            ->exists();

        if ($exists) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Save attendance log.
        |--------------------------------------------------------------------------
        */

        EmployeeAttendanceLog::create([
            'employee_id' => $employee->id,

            'auth_date_time' => $authDateTime,

            'auth_date' => $authDateTime->toDateString(),

            'auth_time' => $authDateTime->toTimeString(),

            'direction' => $direction,

            'device_name' => $deviceName,

            'device_sn' => $deviceSerial,

            'person_name' => $personName,

            'card_no' => $cardNo,

            'notified_at' => null,

            'broadcasted_at' => null,
        ]);

        return true;
    }
}

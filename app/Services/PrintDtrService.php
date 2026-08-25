<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeAttendanceLog;
use App\Models\EmployeeAttendanceLogEvents;
use App\Models\EmployeeBiometricId;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\OperatingUnit;
use App\Models\UniversityActivity;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Arr;

class PrintDtrService
{
    public function printDailyTimeRecord($request)
    {
        $month = $request->month; // e.g., "September"
        $year = $request->year;   // e.g., "2025"
        $cutOff = $request->cut_off; // e.g., 1 or 2

        $monthNumber = Carbon::parse("1 $request->month $request->year")->month;

        $holidays = Holiday::whereMonth('date', $monthNumber)
            ->whereHas('operatingUnits', function ($query) use ($request) {
                $empIds = Arr::flatten((array) $request->input('emp_id', []));
                $query->whereIn('operating_units.id', function ($subQuery) use ($empIds) {
                    $subQuery->select('operating_unit_id')
                        ->from('employees')
                        ->whereIn('id', $empIds);
                });
            })
            ->whereYear('date', $year)
            ->get();


        $startDate = '';
        $endDate = '';

        // Pad single-digit months to two digits (optional safety)
        $month = str_pad($month, 2, '0', STR_PAD_LEFT);


        // dd($month, $year, $cutOff);

        // Create a base date for the given month/year
        $baseDate = "$year-$month-01";

        // dd($baseDate);

        switch ($cutOff) {
            case 1:
                $startDate = date('Y-m-01', strtotime($baseDate));
                $endDate = date('Y-m-15', strtotime($baseDate));
                break;

            default:
                $startDate = date('Y-m-01', strtotime($baseDate));
                $endDate = date('Y-m-t', strtotime($baseDate));
                break;
        }

        // dd($endDate);

        $empIds = Arr::flatten((array) $request->input('emp_id', []));

        $universityActivities = UniversityActivity::whereHas('operatingUnits', function ($query) use ($empIds) {
            $query->whereIn('operating_units.id', function ($subQuery) use ($empIds) {
                $subQuery->select('operating_unit_id')
                    ->from('employees')
                    ->whereIn('id', $empIds);
            });
        })
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_at', [$startDate, $endDate])
                    ->orWhereBetween('end_at', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('start_at', '<=', $startDate)
                            ->where('end_at', '>=', $endDate);
                    });
            })
            ->whereIn('type', ['work_from_home', 'suspension', 'advisory'])
            ->get();

        // Get biometric IDs from employees
        $employeeBiometrics = EmployeeBiometricId::whereIn('employee_id', $empIds)
            ->get()
            ->groupBy('employee_id');

        $allDtrs = [];
        $activityMap = [];

        foreach ($universityActivities as $activity) {
            $period = CarbonPeriod::create($activity->start_at, $activity->end_at);

            foreach ($period as $date) {
                $key = $date->toDateString();

                $activityMap[$key][] = [
                    'id' => $activity->id,
                    'title' => $activity->title ?? $activity->name,
                    'type' => $activity->type,
                    'start_at' => $activity->start_at,
                    'end_at' => $activity->end_at,
                ];
            }
        }

        // foreach ($empIds as $empId) { // OLD CODE
        foreach ($employeeBiometrics as $employeeId => $biometrics) {

            $employeeLeaves = $this->getEmployeeLeaves($employeeId, $startDate, $endDate);
            $employeeEvents = $this->getEmployeeEvents($employeeId, $startDate, $endDate);

            $totalLeaveDays = 0;

            foreach ($employeeLeaves as $duration) {
                switch ($duration) {
                    case 'full_day':
                        $totalLeaveDays += 1;
                        break;

                    case 'half_day_am':
                    case 'half_day_pm':
                        $totalLeaveDays += 0.5;
                        break;
                }
            }


            // Get employee data
            // $employee = Employee::with('personalInformation', 'operatingUnit', 'department', 'position.government_position', 'dailyShiftSchedules')
            //     ->where('biometrics_id', $empId)->first();
            $employee = Employee::with(
                'personalInformation',
                'operatingUnit',
                'department',
                'position.government_position',
                'dailyShiftSchedules'
            )->find($employeeId);

            // dd($employee);

            if (!$employee) {
                continue; // Skip if employee not found
            }

            $biometricIds = $biometrics->pluck('biometric_id')->toArray();

            $logs = EmployeeAttendanceLog::whereIn('employee_id', $biometricIds)
                ->whereBetween('auth_date', [$startDate, $endDate])
                ->orderBy('auth_date_time')
                ->get();

            // Prepare the start and end date range
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);

            // Initialize array to hold daily logs for this employee
            $dtrArray = [];

            // Initialize total undertime and overtime for the employee
            $employeeTotalUndertimeMinutes = 0;
            $employeeTotalOvertimeMinutes = 0;

            $employeeAbsentDays = 0;

            // Loop over each day in the date range
            // foreach ($start->copy()->daysUntil($end->copy()->addDay()) as $date) {
            foreach (CarbonPeriod::create($start, $end) as $date) {
                $dateStr = $date->toDateString();
                $dateFormatted = $date->format('j-M');
                $dateFull = $date->format('Y-m-d');
                $day = $date->format('D'); // or 'l' if you want full name
                $full_day_name = $date->format('l');

                $totalUndertime = 0;
                $totalOvertime = 0;

                // Filter logs for the current day
                $dailyLogs = $logs->filter(fn($log) => $log->auth_date === $dateStr);
                $leaveType = $employeeLeaves[$dateStr] ?? null;
                $events = $employeeEvents[$dateStr] ?? [];
                $activities = $activityMap[$dateStr] ?? [];


                // Map the required DTR fields for the day
                $mapped = [
                    'date_full' => $dateFull,
                    'date' => $dateFormatted,
                    'day' => $day,
                    'check_in' => null,
                    'check_in_device' => null,
                    'break_out' => null,
                    'break_out_device' => null,
                    'break_in' => null,
                    'break_in_device' => null,
                    'check_out' => null,
                    'check_out_device' => null,
                    'ut' => null,
                    'ot' => null,
                    'leave' => $leaveType,
                    'events' => $events,
                    'activities' => $activities,
                    'holidays' => $holidays,
                ];

                // Get the FIRST occurrence of each direction (except check_out which is LAST)
                $checkIn = $dailyLogs->firstWhere('direction', 'check in');
                $breakOut = $dailyLogs->firstWhere('direction', 'break out');
                $breakIn = $dailyLogs->firstWhere('direction', 'break in');
                $checkOut = $dailyLogs->where('direction', 'check out')->last(); // last tap out
                $overtimeIn = $dailyLogs->where('direction', 'overtime in')->last();
                $overtimeOut = $dailyLogs->where('direction', 'overtime out')->last();

                $hasRecord = $checkIn || $breakOut || $breakIn || $checkOut;

                $holiday = $holidays->firstWhere('date', $dateStr);

                // find the matching schedule
                $shift = $employee->dailyShiftSchedules
                    ->firstWhere('day_of_week', $full_day_name);

                $isWorkingDay = $shift !== null;

                $isAbsent =
                    $isWorkingDay &&
                    !$hasRecord &&
                    !$leaveType &&
                    empty($events) &&
                    empty($activities) &&
                    !$holiday;

                if ($isAbsent) {
                    $employeeAbsentDays++;

                    logger()->info('Absent Day', [
                        'employee' => $employee->personalInformation->getFullNameAttributeDesc(),
                        'date' => $dateStr,
                        'hasRecord' => $hasRecord,
                        'leave' => $leaveType,
                        'events' => $events,
                        'activities' => $activities,
                        'holiday' => $holiday?->name,
                        'workingDay' => $isWorkingDay,
                    ]);
                }

                if ($shift) {
                    // Helper closure to remove seconds
                    $normalize = fn($time) => $time ? Carbon::parse($time)->seconds(0) : null;

                    $shiftStart = $normalize($shift->time_in);
                    $shiftEnd = $normalize($shift->time_out);
                    $breakStart = $normalize($shift->break_start);
                    $breakEnd = $normalize($shift->break_end);

                    $actualIn = $normalize($checkIn?->auth_time);
                    $actualOut = $normalize($checkOut?->auth_time);
                    $actualBreakOut = $normalize($breakOut?->auth_time);
                    $actualBreakIn = $normalize($breakIn?->auth_time);
                    $actualOvertimeIn = $normalize($overtimeIn?->auth_time);
                    $actualOvertimeOut = $normalize($overtimeOut?->auth_time);

                    // Late / undertime morning (use seconds for precision)
                    $lateMinutes = $actualIn && $actualIn->gt($shiftStart)
                        ? $shiftStart->diffInSeconds($actualIn) / 60
                        : 0;

                    $breakUndertimeBefore = 0;
                    $breakUndertimeAfter = 0;

                    // New logic to handle flexible break on teaching personnel
                    if ($employee->employee_type === 'Teaching') {
                        $flexStart = Carbon::parse('10:00');
                        $flexEnd = Carbon::parse('14:00');

                        if ($actualBreakOut && $actualBreakIn) {
                            // If break is within the flexible window
                            if (
                                $actualBreakOut->between($flexStart, $flexEnd) &&
                                $actualBreakIn->between($flexStart, $flexEnd)
                            ) {
                                $actualBreakDuration = $actualBreakOut->diffInMinutes($actualBreakIn);

                                // ✅ Only count undertime if the break exceeds 1 hour
                                if ($actualBreakDuration > 60) {
                                    $breakUndertimeAfter = $actualBreakDuration - 60;
                                }
                            } else {
                                // ❌ If outside 10am–2pm, treat full break as undertime
                                $breakUndertimeAfter = $actualBreakOut->diffInMinutes($actualBreakIn);
                            }
                        }
                    } else {
                        // 🧩 Normal fixed break logic
                        $breakUndertimeBefore = $actualBreakOut && $actualBreakOut->lt($breakStart)
                            ? $actualBreakOut->diffInMinutes($breakStart)
                            : 0;

                        $breakUndertimeAfter = $actualBreakIn && $actualBreakIn->gt($breakEnd)
                            ? $breakEnd->diffInMinutes($actualBreakIn)
                            : 0;
                    }

                    // Undertime on check out
                    $checkoutUndertime = $actualOut && $actualOut->lt($shiftEnd)
                        ? $actualOut->diffInSeconds($shiftEnd) / 60
                        : 0;

                    // 🧮 Total undertime minutes
                    $totalUndertime = $lateMinutes + $breakUndertimeBefore + $breakUndertimeAfter + $checkoutUndertime;


                    $employeeTotalUndertimeMinutes += $totalUndertime;

                    // 🔸 Convert undertime minutes to fractional day (8 hours = 480 mins)
                    $undertimeFraction = $totalUndertime / 480;

                    // Overtime Computation
                    $totalOvertime = 0;
                    if ($actualOvertimeIn && $actualOvertimeOut) {
                        $overtimeStartAllowed = $shiftEnd->copy()->addHour(); // 1 hr after schedule

                        $otIn = Carbon::parse($overtimeIn->auth_time);
                        $otOut = Carbon::parse($overtimeOut->auth_time);

                        // ✅ Only count OT if they started after allowed OT time
                        if ($otOut->greaterThan($otIn)) {
                            // If employee started OT early, start counting from the allowed time instead
                            $effectiveStart = $otIn->greaterThan($overtimeStartAllowed)
                                ? $otIn
                                : $overtimeStartAllowed;

                            $rawOvertimeMinutes = $effectiveStart->diffInSeconds($otOut) / 60;

                            // Deduct 1 hour (60 mins) break for every 3 hours (180 mins) of OT
                            $breaksToDeduct = floor($rawOvertimeMinutes / 180) * 60;

                            $totalOvertime = max($rawOvertimeMinutes - $breaksToDeduct, 0);
                        }
                    }

                    $employeeTotalOvertimeMinutes += $totalOvertime;

                    // 🔸 Convert overtime minutes to fractional day
                    $overtimeFraction = $totalOvertime / 480;

                    // 🧩 Truncate both to 3 decimal places (no rounding)
                    $undertimeFraction = $this->truncateDecimals($undertimeFraction, 3);
                    $overtimeFraction = $this->truncateDecimals($overtimeFraction, 3);

                    // ✅ Example debug output
                    // Log::info("Undertime: {$totalUndertime} mins ({$undertimeFraction} day)");
                    // Log::info("Overtime: {$totalOvertime} mins ({$overtimeFraction} day)");
                }


                if ($checkIn) {
                    $mapped['check_in'] = date('H:i', strtotime($checkIn->auth_time));
                    $mapped['check_in_device'] = $checkIn->device_name;
                }

                if ($breakOut) {
                    $mapped['break_out'] = date('H:i', strtotime($breakOut->auth_time));
                    $mapped['break_out_device'] = $breakOut->device_name;
                }

                if ($breakIn) {
                    $mapped['break_in'] = date('H:i', strtotime($breakIn->auth_time));
                    $mapped['break_in_device'] = $breakIn->device_name;
                }

                if ($checkOut) {
                    $mapped['check_out'] = date('H:i', strtotime($checkOut->auth_time));
                    $mapped['check_out_device'] = $checkOut->device_name;
                }

                if ($overtimeIn)
                    $mapped['overtime_in'] = date('H:i', strtotime($overtimeIn->auth_time));
                if ($overtimeOut)
                    $mapped['overtime_out'] = date('H:i', strtotime($overtimeOut->auth_time));
                if ($totalUndertime)
                    $mapped['ut'] = $undertimeFraction;
                if ($totalOvertime)
                    $mapped['ot'] = $overtimeFraction;

                // dd($mapped);

                $dtrArray[] = $mapped;
            }

            $totalUndertimeFraction = $this->truncateDecimals(
                $employeeTotalUndertimeMinutes / 480,
                3
            );

            $totalOvertimeFraction = $this->truncateDecimals(
                $employeeTotalOvertimeMinutes / 480,
                3
            );

            // dd($employee->personalInformation->getFullNameAttributeDesc(), $employeeAbsentDays);

            // Add the employee data and their DTR to the result array
            $allDtrs[] = [
                'operatingUnit' => $employee->operatingUnit->shortcut,
                'employeeName' => $employee->personalInformation->getFullNameAttributeDesc(),
                'position' => $employee->position?->government_position->name ?? "",
                'department' => $employee->department?->shortcut ?? "",
                'dtrArray' => $dtrArray,

                // totals
                'totalUndertimeMinutes' => $employeeTotalUndertimeMinutes,
                'totalUndertimeFraction' => $totalUndertimeFraction,

                'totalOvertimeMinutes' => $employeeTotalOvertimeMinutes,
                'totalOvertimeFraction' => $totalOvertimeFraction,

                'totalLeaveDays' => $totalLeaveDays,
                'totalAbsentDays' => $employeeAbsentDays,
            ];
        }

        // Now, you can return or process $allDtrs as needed

        $monthNumber = Carbon::parse("1 $month $year")->format('m');
        $monthName = Carbon::createFromFormat('m', $monthNumber)->format('M');

        // dd($monthNumber, $monthName);

        // Get number of days in that month
        $daysInMonth = Carbon::createFromDate($year, $monthNumber, 1)->daysInMonth;

        // dd($daysInMonth);
        // dd($allDtrs);

        $pdf = SnappyPdf::loadView('templates/dailytimerecord/dtr', [
            'year' => $year,
            'monthNumber' => $monthNumber,
            'allDtrs' => $allDtrs,
            'monthName' => $monthName,
            'daysInMonth' => $daysInMonth,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'holidays' => $holidays,
        ])
            ->setOption('page-width', '215.9mm')
            ->setOption('page-height', '330.2mm');

        // return $pdf->stream('dtr.pdf');

        return $pdf;
    }

    private function truncateDecimals($number, $decimals = 3)
    {
        $factor = pow(10, $decimals);
        return floor($number * $factor) / $factor;
    }

    private function getEmployeeLeaves($employeeId, $startDate, $endDate)
    {
        $approvedLeaves = LeaveApplication::with('leaveDates', 'leaveStatuses')
            ->where('employee_id', $employeeId)
            ->whereHas('leaveStatuses', function ($query) {
                $query->where('status', 'approved')
                    ->whereRaw('created_at = (
                  SELECT MAX(created_at)
                  FROM leave_statuses
                  WHERE leave_statuses.leave_application_id = leave_applications.id
              )');
            })
            ->whereHas('leaveDates', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })
            ->get();

        $leaveMap = [];

        foreach ($approvedLeaves as $leave) {
            foreach ($leave->leaveDates as $leaveDate) {
                $date = $leaveDate->date;

                // Only include dates within range (extra safety)
                if ($date >= $startDate && $date <= $endDate) {
                    $leaveMap[$date] = $leaveDate->duration;
                    // full_day | half_day_am | half_day_pm
                }
            }
        }

        return $leaveMap;
    }

    private function getEmployeeEvents($employeeId, $startDate, $endDate)
    {
        $employeeEvents = EmployeeAttendanceLogEvents::with('attendanceLogEventType')
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', 'approved') // 👈 IMPORTANT
            ->get()
            ->groupBy('date');

        $eventsMap = [];

        foreach ($employeeEvents as $date => $events) {
            $eventsMap[$date] = $events->map(function ($event) {
                return [
                    'id' => $event->att_log_event_type_id,
                    'name' => $event->attendanceLogEventType->name ?? null,
                    'coverage' => $event->coverage, // whole_day / am / pm / custom
                    'start_time' => $event->start_time,
                    'end_time' => $event->end_time,
                    'remarks' => $event->remarks,
                ];
            })->toArray();
        }

        return $eventsMap;
    }

    // Previous scopeVisibleTo method for reference (not used in this service but kept for context)
}

<?php

namespace App\Services;

use App\Events\NewDtrEntry;
use App\Http\Filters\EmployeeFilter;
use App\Http\Filters\LeavesFilter;
use App\Http\Filters\MyLeavesFilter;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\EmployeeTableResource;
use App\Http\Resources\LeaveApplicationListResource;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeAttendanceLog;
use App\Models\EmployeeAttendanceLogDocuments;
use App\Models\EmployeeAttendanceLogEvents;
use App\Models\EmployeeBiometricId;
use App\Models\EmployeeDtrValidation;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\OperatingUnit;
use App\Models\PersonalInformation;
use App\Models\UniversityActivity;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class DailyTimeRecordService
{
    public function index()
    {
        $direction = 'ASC';
        if (request('direction') && request('direction') === 'Descending') {
            $direction = 'DESC';
        }

        $user = Auth::user();

        $currentDate = Carbon::now();
        $renderDays = 30; // Fixed 30-day rendering period

        $employee = Employee::find($user->employee_id);

        $query = Employee::with([
            'personalInformation',
            'department',
            'jobStatus',
            'biometricIds',
        ]);

        $filter = new EmployeeFilter(request()->all());
        $query = $filter->apply($query);
        $query->whereNull('date_separated')
            ->orWhere(function ($query) use ($currentDate, $renderDays) {
                $query->whereDate('date_separated', '<', $currentDate)  // Already separated before today
                    ->whereDate('date_separated', '>=', $currentDate->copy()->subDays($renderDays)); // Within last 30 days
            });

        // Restrict based on role
        if (!($user->hasRole('superadmin') || $user->hasRole('hr_director'))) {
            if ($user->hasRole('college_secretary')) {
                // College secretary → restrict by department
                $query->where('department_id', $employee->department_id);
            } else {
                // Other employees → restrict by operating unit or detailed operating unit
                $query->where(function ($q) use ($employee) {
                    $q->where('operating_unit_id', $employee->operating_unit_id)
                        ->orWhere('detailed_at', $employee->operating_unit_id);
                });
            }
        }

        $query->whereHas('biometricIds', function ($q) {
            $q->whereNotNull('biometric_id');
        });
        // $query = $query->whereNotNull('biometrics_id');

        $size = request()->input('size', 10);
        $query->orderBy(
            PersonalInformation::select('lastname')
                ->whereColumn('employee_id', 'employees.id')
                ->limit(1),
            $direction
        );

        $employees = $query->paginate($size)->through(function ($employee) use ($user) {
            $employee->redirect_link = $employee->id === $user->employee_id ? route('self-service.my-dtr.index')
                : URL::signedRoute(
                    'hrmanagement.dailytimerecord.view',
                    ['id' => $employee->id]
                );
            return $employee;
        })->withQueryString();

        // dd($employees);

        return EmployeeTableResource::collection($employees);
    }

     public function getEmployeeAttendanceLogsEvents($employee_id, $monthSelected, $yearSelected){
        //fetch employee attendance logs.
        $employeeAttendanceLogs = EmployeeAttendanceLogEvents::with('attendanceLogEventType')->where('employee_id', $employee_id)
            ->whereMonth('date', $monthSelected)
            ->whereYear('date', $yearSelected)
            ->get();

        return $employeeAttendanceLogs;
    }

    public function getOperatingUnits()
    {
        if (Auth::user()->hasRole('superadmin') || Auth::user()->hasRole('hr_director')) {
            $operatingUnits = OperatingUnit::get();
        } else {
            $operatingUnits = OperatingUnit::where('id', Auth::user()->employee->operating_unit_id)->get();
        }
        return $operatingUnits;
    }
    public function getDepartmentsByOperatingUnit(string $operating_unit_id)
    {
        $departments = Department::where('operating_unit_id', $operating_unit_id)->get();
        return $departments;
    }

    public function employeeLeaveApplications(string $employee_id)
    {
        $query = LeaveApplication::with([
            'leave',
            'employee',
            'specialLeaveCredit.specialLeave',
            'leaveDates',
            'currentStatus'
        ])
            ->where('employee_id', $employee_id)

            // ❌ EXCLUDE CANCELLED LEAVES
            ->whereHas('currentStatus', function ($q) {
                $q->whereNotIn('status', ['cancelled', 'disapproved']);
            });

        $filter = new LeavesFilter(request()->all());
        $query = $filter->apply($query);

        $myLeaveApplications = $query->paginate(10);

        foreach ($myLeaveApplications as $leaveApplication) {
            $leaveApplication->view_link = URL::signedRoute(
                'hrmanagement.leave.printLeaveApplication',
                ['id' => $leaveApplication->id]
            );
        }

        return LeaveApplicationListResource::collection($myLeaveApplications);
    }


    public function employeeDailyTimeRecords($employee_id, $monthSelected, $yearSelected)
    {

        $employee = Employee::with('dailyShiftSchedules')->where('id', $employee_id)->first();

        $holidays = Holiday::whereMonth('date', $monthSelected)
            ->whereHas('operatingUnits', function ($q) use ($employee) {
                $q->where('operating_units.id', $employee->operating_unit_id)
                    ->orWhere('operating_units.id', $employee->detailed_at);
            })
            ->whereYear('date', $yearSelected)
            ->get();
        // Get Employee Number
        $bioIds = EmployeeBiometricId::where('employee_id', $employee_id)
            ->pluck('biometric_id')
            ->toArray();

        // First and last day of the month (formatted as YYYY-MM-DD)
        $startOfMonth = Carbon::createFromDate($yearSelected, $monthSelected, 1)->startOfDay()->format('Y-m-d');
        $endOfMonth = Carbon::createFromDate($yearSelected, $monthSelected, 1)->endOfMonth()->endOfDay()->format('Y-m-d');

        // dd($startOfMonth);

        // Get logs between first and last day of month
        $employeeAttendanceLogs = EmployeeAttendanceLog::whereIn('employee_id', $bioIds)
            ->whereBetween('auth_date', [$startOfMonth, $endOfMonth])
            ->orderBy('auth_date_time')
            ->get();

        $attendanceEvents = EmployeeAttendanceLogEvents::with(['documents', 'attendanceLogEventType'])
            ->where('employee_id', $employee_id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->get()
            ->keyBy('date');

        $universityActivities = UniversityActivity::where(function ($query) use ($startOfMonth, $endOfMonth) {
            $query->where('start_at', '<=', $endOfMonth)
                ->where('end_at', '>=', $startOfMonth);
        })->get();

        $daysPresent = $employeeAttendanceLogs
            ->filter(function ($log) {
                return in_array($log->direction, ['check out']);
            })
            ->pluck('auth_date') // get the dates only
            ->unique()           // avoid counting multiple check outs on same day
            ->count();

        $lateDays = 0;
        $weekendDaysWorked = 0;

        // dd($employeeAttendanceLogs);

        // Prepare the start and end date range
        $start = Carbon::parse($startOfMonth);
        $end = Carbon::parse($endOfMonth);

        // Initialize array to hold daily logs for this employee
        $dtrArray = [];

        // Loop over each day in the date range
        foreach ($start->copy()->daysUntil($end->copy()) as $date) {
            $dateStr = $date->toDateString();
            $dateFormatted = $date->format('j-M');
            $dateFull = $date->format('Y-m-d');
            $day = $date->format('l'); // Full name of the day
            $full_day_name = $date->format('l');

            $totalUndertime = 0;
            $totalOvertime = 0;

            // Filter logs for the current day
            $dailyLogs = $employeeAttendanceLogs->filter(fn($log) => $log->auth_date === $dateStr);

            // Map the required DTR fields for the day
            $mapped = [
                'date_full' => $dateFull,
                'date' => $dateFormatted,
                'day' => $day,
                'check_in' => null,
                'break_out' => null,
                'break_in' => null,
                'check_out' => null,
                'ut' => null,
                'ot' => null,
                'university_activity' => null,
            ];

            // Attach any university activities
            $activitiesForDay = $universityActivities->filter(function ($activity) use ($dateStr) {
                return $dateStr >= Carbon::parse($activity->start_at)->toDateString() &&
                    $dateStr <= Carbon::parse($activity->end_at)->toDateString();
            });

            if ($activitiesForDay->isNotEmpty()) {
                $activityLabels = [];
                $meridian = 'WHOLE DAY';
                foreach ($activitiesForDay as $activity) {
                    $typeLabel = str_replace('_', ' ', $activity->type);
                    $activityLabels[] = $activity->title . ($typeLabel ? " ($typeLabel)" : "");
                    $meridian = strtoupper($activity->meridian ?? 'WHOLE DAY');
                }
                $mapped['university_activity'] = [
                    'label' => implode(' / ', $activityLabels),
                    'meridian' => $meridian
                ];
            }

            // Attach any out-of-office event (official travel/business) for this date
            $eventForDay = $attendanceEvents->get($dateStr);
            if ($eventForDay) {
                $mapped['event'] = [
                    'id' => $eventForDay->id,
                    'type' => optional($eventForDay->attendanceLogEventType)->name,
                    'att_log_event_type_id' => $eventForDay->att_log_event_type_id,
                    'coverage' => $eventForDay->coverage,
                    'start_time' => $eventForDay->start_time ? Carbon::parse($eventForDay->start_time)->format('H:i') : null,
                    'end_time' => $eventForDay->end_time ? Carbon::parse($eventForDay->end_time)->format('H:i') : null,
                    'remarks' => $eventForDay->remarks,
                    'status' => $eventForDay->status,
                    'source' => $eventForDay->source,
                    'documents' => $eventForDay->documents->map(function ($doc) {
                        return [
                            'id' => $doc->id,
                            'file_path' => $doc->file_path,
                            'file_name' => basename($doc->file_path),
                            'url' => asset('storage/' . $doc->file_path),
                        ];
                    })->toArray(),
                ];
            }

            // Get the FIRST occurrence of each direction (except check_out which is LAST)
            $checkIn = $dailyLogs->firstWhere('direction', 'check in');
            $breakOut = $dailyLogs->firstWhere('direction', 'break out');
            $breakIn = $dailyLogs->firstWhere('direction', 'break in');
            $checkOut = $dailyLogs->where('direction', 'check out')->last(); // last tap out
            $overtimeIn = $dailyLogs->where('direction', 'overtime in')->last();
            $overtimeOut = $dailyLogs->where('direction', 'overtime out')->last();

            // find the matching schedule
            $shift = $employee->dailyShiftSchedules
                ->firstWhere('day_of_week', $full_day_name);

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

                // ✅ Count as late day if lateMinutes > 0
                if ($lateMinutes > 0) {
                    $lateDays += 1;
                }

                $breakUndertimeBefore = 0;
                $breakUndertimeAfter = 0;

                // // Break undertime before
                // $breakUndertimeBefore = $actualBreakOut && $actualBreakOut->lt($breakStart)
                //     ? $actualBreakOut->diffInSeconds($breakStart) / 60
                //     : 0;

                // // Break undertime after
                // $breakUndertimeAfter = $actualBreakIn && $actualBreakIn->gt($breakEnd)
                //     ? $breakEnd->diffInSeconds($actualBreakIn) / 60
                //     : 0;

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

                // 🔸 Convert overtime minutes to fractional day
                $overtimeFraction = $totalOvertime / 480;

                // 🧩 Truncate both to 3 decimal places (no rounding)
                $undertimeFraction = $this->truncateDecimals($undertimeFraction, 3);
                $overtimeFraction = $this->truncateDecimals($overtimeFraction, 3);

                // ✅ Example debug output
                // Log::info("Undertime: {$totalUndertime} mins ({$undertimeFraction} day)");
                // Log::info("Overtime: {$totalOvertime} mins ({$overtimeFraction} day)");
            }


            if ($checkIn)
                $mapped['check_in'] = date('H:i', strtotime($checkIn->auth_time));
            if ($breakOut)
                $mapped['break_out'] = date('H:i', strtotime($breakOut->auth_time));
            if ($breakIn)
                $mapped['break_in'] = date('H:i', strtotime($breakIn->auth_time));
            if ($checkOut)
                $mapped['check_out'] = date('H:i', strtotime($checkOut->auth_time));
            if ($overtimeIn)
                $mapped['overtime_in'] = date('H:i', strtotime($overtimeIn->auth_time));
            if ($overtimeOut)
                $mapped['overtime_out'] = date('H:i', strtotime($overtimeOut->auth_time));
            if ($totalUndertime)
                $mapped['ut'] = $undertimeFraction;
            if ($totalOvertime)
                $mapped['ot'] = $overtimeFraction;

            // Calculate total rendered minutes for UI toggle logic (University Activities & Events)
            if (isset($mapped['university_activity']) || isset($mapped['event'])) {
                $actualLogMins = 0;
                if ($checkIn && $checkOut) {
                    $startT = Carbon::parse($checkIn->auth_time);
                    $endT = Carbon::parse($checkOut->auth_time);
                    $actualLogMins = $startT->diffInMinutes($endT);

                    if ($breakOut && $breakIn) {
                        $actualLogMins -= Carbon::parse($breakOut->auth_time)->diffInMinutes(Carbon::parse($breakIn->auth_time));
                    } else if ($startT->hour < 12 && $endT->hour >= 13) {
                        $actualLogMins -= 60; // Standard break deduction
                    }
                }

                $eventMinutes = 0;
                if (isset($mapped['event'])) {
                    if ($mapped['event']['coverage'] === 'whole_day') {
                        $eventMinutes = 480;
                    } elseif (in_array($mapped['event']['coverage'], ['am', 'pm'])) {
                        $eventMinutes = 240;
                    } elseif ($mapped['event']['coverage'] === 'custom' && $mapped['event']['start_time'] && $mapped['event']['end_time']) {
                        $eventMinutes = Carbon::parse($mapped['event']['start_time'])->diffInMinutes(Carbon::parse($mapped['event']['end_time']));
                    }
                } elseif (isset($mapped['university_activity'])) {
                    if ($mapped['university_activity']['meridian'] === 'WHOLE DAY') {
                        $eventMinutes = 480;
                    } else {
                        $eventMinutes = 240;
                    }
                }
                $mapped['total_rendered_minutes'] = max(0, $actualLogMins + $eventMinutes);
            }

            $dtrArray[] = $mapped;

            // ✅ Weekend work check
            $isWeekend = in_array($full_day_name, ['Saturday', 'Sunday']);
            $hasWork = $checkIn || $checkOut || $overtimeIn || $overtimeOut;

            if ($isWeekend && $hasWork) {
                $weekendDaysWorked += 1;
            }

            // dd($dtrArray);
        }


        // dd($weekendDaysWorked);

        return [
            'dtrArray' => $dtrArray,
            'holidays' => $holidays,
            'presentDays' => $daysPresent,
            'lateDays' => $lateDays,
            'weekendDaysWorked' => $weekendDaysWorked,
        ];
    }

    private function truncateDecimals($number, $decimals = 3)
    {
        $factor = pow(10, $decimals);
        return floor($number * $factor) / $factor;
    }

    public function approve($data)
    {
        $dtr = EmployeeDtrValidation::updateOrCreate(
            [
                'employee_id' => $data['employee_id'],
                'month' => $data['month'],
                'year' => $data['year'],
                'period' => $data['period'],
            ],
            [
                'total_time_rendered' => $data['total_time_rendered'],
                'total_undertime' => $data['total_undertime'],
                'total_overtime' => $data['total_overtime'],
                'total_special_time' => $data['total_special_time'] ?? 0,
                'status' => 'approved',
                'last_updated_by' => auth()->id(),
                'last_activity_at' => now(),
            ]
        );

        return $dtr;
    }

    public function updateTimeRecord($request)
    {

        $employee = Employee::with('personalInformation')->where('id', $request['employee_id'])->first();

        $bio_id = $employee->biometrics_id;
        $device_name = 'USDO';
        $device_serial = 'FN1091957';
        $person_name = $employee->personalInformation->getFirstLastNameAttribute();
        $card_no = ' ';


        if ($request['check_in'] != null && $request['break_out'] == null && $request['break_in'] == null && $request['check_out'] == null) {
            //if id is not null update
            //if id is null create
            $employeeAttendanceLog = EmployeeAttendanceLog::updateOrCreate([
                'id' => $request['rowId'],
            ], [
                'employee_id' => $bio_id,
                'auth_date_time' => $request['selected_date'] . ' ' . $request['check_in'],
                'auth_date' => $request['selected_date'],
                'auth_time' => $request['check_in'],
                'direction' => 'check in',
                'device_name' => $device_name,
                'device_sn' => $device_serial,
                'person_name' => $person_name,
                'card_no' => $card_no,
            ]);
            return $employeeAttendanceLog;
        } else if ($request['break_out'] != null && $request['check_in'] == null && $request['break_in'] == null && $request['check_out'] == null) {
            $employeeAttendanceLog = EmployeeAttendanceLog::updateOrCreate([
                'id' => $request['rowId'],
            ], [
                'employee_id' => $bio_id,
                'auth_date_time' => $request['selected_date'] . ' ' . $request['break_out'],
                'auth_date' => $request['selected_date'],
                'auth_time' => $request['break_out'],
                'direction' => 'break out',
                'device_name' => $device_name,
                'device_sn' => $device_serial,
                'person_name' => $person_name,
                'card_no' => $card_no,
            ]);
            return $employeeAttendanceLog;
        } else if ($request['break_in'] != null && $request['check_in'] == null && $request['break_out'] == null && $request['check_out'] == null) {
            $employeeAttendanceLog = EmployeeAttendanceLog::updateOrCreate([
                'id' => $request['rowId'],
            ], [
                'employee_id' => $bio_id,
                'auth_date_time' => $request['selected_date'] . ' ' . $request['break_in'],
                'auth_date' => $request['selected_date'],
                'auth_time' => $request['break_in'],
                'direction' => 'break in',
                'device_name' => $device_name,
                'device_sn' => $device_serial,
                'person_name' => $person_name,
                'card_no' => $card_no,
            ]);
            return $employeeAttendanceLog;
        } else if ($request['check_out'] != null && $request['check_in'] == null && $request['break_out'] == null && $request['break_in'] == null) {
            $employeeAttendanceLog = EmployeeAttendanceLog::updateOrCreate([
                'id' => $request['rowId'],
            ], [
                'employee_id' => $bio_id,
                'auth_date_time' => $request['selected_date'] . ' ' . $request['check_out'],
                'auth_date' => $request['selected_date'],
                'auth_time' => $request['check_out'],
                'direction' => 'check out',
                'device_name' => $device_name,
                'device_sn' => $device_serial,
                'person_name' => $person_name,
                'card_no' => $card_no,
            ]);
            return $employeeAttendanceLog;
        }
    }

    public function pullData($biometrics_id)
    {
        $employeeAttendanceLog = EmployeeAttendanceLog::where('employee_id', $biometrics_id)
            ->where('broadcasted_at', null)
            ->get();

        if (!$employeeAttendanceLog->isEmpty()) {
            foreach ($employeeAttendanceLog as $employeeAttendanceLogItem) {
                broadcast(new NewDtrEntry($employeeAttendanceLogItem));
                $employeeAttendanceLogItem->update([
                    'broadcasted_at' => now(),
                ]);
            }
        }

        // dd($employeeAttendanceLog);

        return $employeeAttendanceLog;
    }

    public function storeAttendanceEvent(Employee $employee, $validated, $documents = []): EmployeeAttendanceLogEvents
    {

        Log::channel('input')->info('DailyTimeRecordService@storeAttendanceEvent called', [
            'employee_id' => $employee->id,
            'date' => $validated['date'] ?? null,
            'att_log_event_type_id' => $validated['att_log_event_type_id'] ?? null,
            'documents_count' => count($documents),
        ]);

        try {
            $event = DB::transaction(function () use ($employee, $validated, $documents) {
                // One event per employee + date (enforced by unique index)
                $event = EmployeeAttendanceLogEvents::updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'date' => $validated['date'],
                    ],
                    [
                        'att_log_event_type_id' => $validated['att_log_event_type_id'],
                        'coverage' => $validated['coverage'] ?? 'whole_day',
                        'start_time' => $validated['coverage'] === 'custom' ? ($validated['start_time'] ?? null) : null,
                        'end_time' => $validated['coverage'] === 'custom' ? ($validated['end_time'] ?? null) : null,
                        'remarks' => $validated['remarks'] ?? null,
                        'source' => 'employee_upload',
                        'status' => 'approved',
                        'approved_by' => null,
                    ]
                );

                // Attach any uploaded supporting documents
                foreach ($documents as $file) {
                    if (!$file->isValid()) {
                        continue;
                    }

                    // 1. Get the original filename (e.g., "MyDocument.pdf")
                    $originalName = $file->getClientOriginalName();

                    // 2. Optional: Clean the name and add a timestamp to prevent overwriting
                    $safeName = time() . '_' . str_replace([' ', '#', '%'], '_', $originalName);

                    // 3. Use storeAs instead of store
                    $path = $file->storeAs(
                        "employee_attendance_events/{$employee->id}",
                        $safeName,
                        'public'
                    );

                    EmployeeAttendanceLogDocuments::create([
                        'log_event_id' => $event->id,
                        'file_path' => $path,
                        'uploaded_by' => $employee->id,
                    ]);
                }


                return $event;
            });

            Log::channel('output')->info('DailyTimeRecordService@storeAttendanceEvent success', [
                'event_id' => $event->id,
                'employee_id' => $employee->id,
            ]);

            return $event;
        } catch (\Throwable $e) {
            Log::channel('error')->error('DailyTimeRecordService@storeAttendanceEvent failed', [
                'employee_id' => $employee->id,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function deleteAttendanceEvent(Employee $employee, $eventId)
    {
        $event = EmployeeAttendanceLogEvents::where('id', $eventId)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        return DB::transaction(function () use ($event) {
            // Delete associated documents and files
            foreach ($event->documents as $document) {
                if (Storage::disk('public')->exists($document->file_path)) {
                    Storage::disk('public')->delete($document->file_path);
                }
                $document->delete();
            }

            // Delete the event
            $event->delete();

            return true;
        });
    }

    public function deleteAttendanceDocument(Employee $employee, $documentId)
    {
        $document = EmployeeAttendanceLogDocuments::where('id', $documentId)
            ->whereHas('logEvent', function ($query) use ($employee) {
                $query->where('employee_id', $employee->id);
            })
            ->firstOrFail();

        if (Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        return $document->delete();
    }
}

<?php

namespace App\Services;

use App\Http\Filters\MyLeavesFilter;
use App\Http\Resources\EmployeeLeaveCreditsHistoryResource;
use App\Http\Resources\MyLeaveApplicationResource;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeLeaveCreditsHistory;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\LeaveApplication;
use App\Models\LeaveApplicationDate;
use App\Notifications\LeaveApplicationNotification;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class MyLeavesService
{
    public function index()
    {
        // Count Total Records of Leave Available for Employee
        // Count The Total Balance of all Leaves
        $employee_id = Auth::user()->employee_id;
        $employeeLeaveCreditsHistory = EmployeeLeaveCreditsHistory::with('leaveType')
            ->join('leaves', 'leaves.id', '=', 'employee_leave_credits_histories.leave_id')
            ->where('leaves.name', '!=', 'Others')
            ->where('employee_leave_credits_histories.employee_id', $employee_id)
            ->whereIn('employee_leave_credits_histories.id', function ($query) use ($employee_id) {
                $query->selectRaw('MAX(id)')
                    ->from('employee_leave_credits_histories as elch2')
                    ->whereColumn('elch2.leave_id', 'employee_leave_credits_histories.leave_id')
                    ->where('elch2.employee_id', $employee_id);
            })
            ->select(
                'employee_leave_credits_histories.leave_id',
                'employee_leave_credits_histories.balance as total_balance',
                'employee_leave_credits_histories.total_earned'
            )
            ->get();

        $specialLeaveCreditsHistory = EmployeeLeaveCreditsHistoryResource::collection(
            EmployeeLeaveCreditsHistory::with('specialLeave')
                ->where('employee_id', $employee_id)
                ->whereNotNull('special_leave_id')
                ->where('balance', '!=', 0)
                ->whereIn('id', function ($query) use ($employee_id) {
                    $query->selectRaw('MAX(id)')
                        ->from('employee_leave_credits_histories')
                        ->where('employee_id', $employee_id)
                        ->whereNotNull('special_leave_id')
                        ->groupBy('document_type_number');
                })
                ->get()
        );

        $totalLeaveCredits = EmployeeLeaveCreditsHistory::where('employee_id', $employee_id)->sum('balance');
        $totalLeaveTypes = EmployeeLeaveCreditsHistory::with('leaveType')->where('employee_id', $employee_id)
            ->whereHas('leaveType', function ($query) {
                $query->where('name', '!=', 'Others');
            })
            ->groupBy('leave_id')->count('leave_id');
        return [
            'employeeLeaveCreditsHistory' => $employeeLeaveCreditsHistory,
            'specialLeaveCreditsHistory' => $specialLeaveCreditsHistory,
            'totalLeaveCredits' => $totalLeaveCredits,
            'totalLeaveTypes' => $totalLeaveTypes,
        ];
    }

    public function leaveTypes()
    {
        return Leave::whereIn('name', [
            'Vacation Leave',
            'Mandatory/Forced Leave',
            'Sick Leave',
            'Maternity Leave',
            'Paternity Leave',
            'Special Privilege Leave',
            'Solo Parent Leave',
            'Study Leave',
            'Rehabilitation Privilege',
            'Special Leave Benefits for Women',
            'Special Emergency (Calamity) Leave',
            'Adoption Leave',
            'Others'
        ])
            ->select('id', 'name')
            ->get();
    }

    public function myLeaveApplications()
    {
        $employee_id = Auth::user()->employee_id;

        $query = LeaveApplication::with([
            'leave',
            'employee',
            'specialLeaveCredit.specialLeave',
            'leaveDates',
            'currentStatus'
        ])
            ->where('employee_id', $employee_id);

        // // ❌ EXCLUDE CANCELLED LEAVES
        // ->whereHas('currentStatus', function ($q) {
        //     $q->whereNotIn('status', ['cancelled', 'disapproved']);
        // });

        $filter = new MyLeavesFilter(request()->all());
        $query = $filter->apply($query);

        $myLeaveApplications = $query->paginate(10);

        foreach ($myLeaveApplications as $leaveApplication) {
            $leaveApplication->view_link = URL::signedRoute(
                'self-service.my-leaves.view',
                ['id' => $leaveApplication->id]
            );
        }

        return MyLeaveApplicationResource::collection($myLeaveApplications);
    }


    // New logic for displaying leave credits including service credits
    public function checkLeaveCreditsBalance(array $validated)
    {
        $employee_id = Auth::user()->employee_id;
        $key = $validated['leaveType'] ?? null;
        $leaveType = Leave::where('name', $key)->first();

        if (!$leaveType) {
            return [
                'balance' => 0,
                'total_earned' => 0,
                'credit_addition' => 0,
                'credit_deduction' => 0,
                'has_credits' => false,
                'leave_type' => $key ?? 'Unknown',
            ];
        }

        // Get latest record for the requested leave type
        $employeeLeaveCreditsHistory = EmployeeLeaveCreditsHistory::where('employee_id', $employee_id)
            ->where('leave_id', $leaveType->id)
            ->latest()
            ->first();

        // Get latest Service Credit record
        $serviceCredit = Leave::where('name', 'Service Credits')->first();
        $serviceCreditHistory = $serviceCredit
            ? EmployeeLeaveCreditsHistory::where('employee_id', $employee_id)
                ->where('leave_id', $serviceCredit->id)
                ->latest()
                ->first()
            : null;

        // Default values
        $balance = $employeeLeaveCreditsHistory->balance ?? 0;
        $total_earned = $employeeLeaveCreditsHistory->total_earned ?? 0;
        $credit_addition = $employeeLeaveCreditsHistory->credit_addition ?? 0;
        $credit_deduction = $employeeLeaveCreditsHistory->credit_deduction ?? 0;

        // ✅ Teaching Employee Rule: Combine Service Credits with VL or SL
        if (in_array($leaveType->name, ['Vacation Leave', 'Sick Leave'])) {
            $serviceBalance = $serviceCreditHistory->balance ?? 0;

            if ($serviceBalance > 0) {
                if ($balance > 0) {
                    // Add service credits to VL/SL balance
                    $balance += $serviceBalance;
                } else {
                    // No VL/SL balance → use service credits as balance
                    $balance = $serviceBalance;
                }
            }
        }

        return [
            'balance' => $balance,
            'total_earned' => $total_earned,
            'credit_addition' => $credit_addition,
            'credit_deduction' => $credit_deduction,
            'has_credits' => $balance > 0,
            'leave_type' => $leaveType->name,
        ];
    }

    public function computeForUnpaidLeaves($leaveType, $leaveDates)
    {
        $employee = Auth::user()->employee;

        // 1. Get leave
        $leave = Leave::where('name', $leaveType)->firstOrFail();
        $leaveId = $leave->id;

        $isTeaching = $employee->employee_type === 'Teaching';
        $hasDesignation = $employee->employeeDesignations()->exists();

        // ✅ Determine which leave ID to use for balance
        $effectiveLeaveId = $leaveId;

        if (
            $isTeaching &&
            !$hasDesignation &&
            in_array($leaveId, [1, 3]) // VL or SL
        ) {
            // 🔥 Use SERVICE CREDIT instead
            $effectiveLeaveId = Leave::where('name', 'Service Credits')->value('id');
        }

        // 2. Latest leave balance
        $latestCredit = (float) EmployeeLeaveCreditsHistory::where('employee_id', $employee->id)
            ->where('leave_id', $effectiveLeaveId)
            ->latest()
            ->value('balance') ?? 0;

        // 3. Work schedule (dynamic, supports Sat/Sun workers)
        $workDays = $employee->dailyShiftSchedules()
            ->pluck('day_of_week')
            ->map(fn($d) => strtolower($d))
            ->toArray();

        // fallback if none
        if (empty($workDays)) {
            $workDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        }

        // 4. Holidays
        $holidays = Holiday::pluck('date')
            ->map(fn($d) => Carbon::parse($d)->toDateString())
            ->toArray();

        // 5. Normalize leave dates
        $dates = collect($leaveDates)
            ->filter(fn($d) => ($d['included'] ?? false) == true)
            ->map(function ($d) {
                return [
                    'date' => Carbon::parse($d['date'])->toDateString(),
                    'type' => $d['type'] ?? 'full_day',
                ];
            });

        // 6. Filter ONLY valid working days (respects custom schedules)
        $validDates = $dates->filter(function ($d) use ($workDays, $holidays) {
            $dayName = strtolower(Carbon::parse($d['date'])->format('l'));

            return in_array($dayName, $workDays)
                && !in_array($d['date'], $holidays);
        });

        // 7. Compute total requested
        $totalRequested = $validDates->sum(function ($d) {
            return in_array($d['type'], ['half_day_am', 'half_day_pm']) ? 0.5 : 1;
        });

        // 8. WITH PAY logic
        // 🔥 Only CTO (special leave) should allow decimals — NOT VL/SL/Service Credit
        $isCTO = false;

        if ($leaveId == 13) {
            $special = EmployeeLeaveCreditsHistory::with('specialLeave')
                ->find(request('specialLeaveCreditId'));

            $specialName = strtolower($special->specialLeave->name ?? '');
            $isCTO = $specialName === 'compensatory overtime credit';
        }

        if ($isCTO) {
            $daysWithPay = min($latestCredit, $totalRequested);
        } else {
            // 🔥 FORCE WHOLE DAYS
            $daysWithPay = min(
                (int) floor($latestCredit),
                (int) ceil($totalRequested)
            );
        }

        // 9. WITHOUT PAY
        $daysWithoutPay = max(0, $totalRequested - $daysWithPay);

        return [
            'totalRequested' => $totalRequested,
            'availableCredits' => $latestCredit,
            'daysWithPay' => $daysWithPay,
            'daysWithoutPay' => $daysWithoutPay,
        ];
    }

    public function storeLeaveApplication(array $validated)
    {
        $employee = $this->getEmployee();
        $leave = $this->getLeave($validated['leaveType']);

        $this->validateGenderEligibility($employee, $leave);

        $scheduleWorkDays = $this->getScheduleWorkDays($employee);

        $leaveDates = $this->prepareLeaveDates(
            $validated['leave_dates'],
            $scheduleWorkDays,
            $leave->name
        );

        if ($leaveDates->isEmpty()) {
            throw new Exception('Please select at least one leave date.');
        }

        $this->validateOverlap($employee, $leaveDates);

        // =========================
        // CREDIT COMPUTATION (FIXED)
        // =========================
        $result = $this->computeCredits(
            $leaveDates,
            $leave->name,
            $employee
        );

        $credits = $result['credits'];
        $leaveDates = $result['validDates'];

        $this->validateCredits($employee, $leave, $credits);

        return DB::transaction(function () use ($employee, $leave, $leaveDates, $credits, $validated) {

            $leaveApplication = $this->createLeaveApplication(
                $employee,
                $leave,
                $leaveDates,
                $credits,
                $validated
            );

            $this->saveLeaveDates($leaveApplication, $leaveDates);

            $this->setInitialStatus($leaveApplication);

            $this->deduct($leaveApplication, $leaveApplication); // ✅ deduct on filing

            $this->notifySupervisor($employee, $leaveApplication);

            return [
                'leaveApplication' => $leaveApplication,
            ];
        });
    }

    private function getEmployee()
    {
        return Employee::with('personalInformation', 'immediateSupervisor', 'weeklyShiftTemplate')
            ->findOrFail(auth()->user()->employee_id);
    }

    private function getLeave($leaveType)
    {
        return Leave::where('name', $leaveType)->firstOrFail();
    }

    private function validateGenderEligibility($employee, $leave)
    {
        $gender = strtolower($employee->personalInformation->sex ?? '');

        if ($leave->name === 'Maternity Leave' && $gender === 'male') {
            throw new Exception('You are not eligible for maternity leave.');
        }

        if ($leave->name === 'Paternity Leave' && $gender === 'female') {
            throw new Exception('You are not eligible for paternity leave.');
        }
    }

    private function getScheduleWorkDays($employee)
    {
        return collect($employee->weeklyShiftTemplate?->weekly_shift_days)
            ->whenEmpty(fn() => collect([
                'Monday',
                'Tuesday',
                'Wednesday',
                'Thursday',
                'Friday'
            ]));
    }

    private function isCalendarBasedLeave($leaveName)
    {
        return in_array($leaveName, [
            'Maternity Leave',
            'Study Leave',
            'Rehabilitation Leave',
            'Special Leave Benefits for Women',
        ]);
    }

    private function prepareLeaveDates($dates, $scheduleWorkDays, $leaveName)
    {
        $isCalendar = $this->isCalendarBasedLeave($leaveName);

        $collection = collect($dates)
            ->filter(fn($d) => $d['included'] ?? true);

        if (!$isCalendar) {
            $collection = $collection->filter(function ($d) use ($scheduleWorkDays) {
                $dayName = \Carbon\Carbon::parse($d['date'])->format('l');
                return $scheduleWorkDays->contains($dayName);
            });
        }

        $collection = $collection->values();

        if ($isCalendar) {
            $this->validateContinuousDates($collection);
        }

        return $collection;
    }

    private function validateContinuousDates($leaveDates)
    {
        $dates = $leaveDates->pluck('date')->sort()->values();

        $expected = \Carbon\Carbon::parse($dates->first());

        foreach ($dates as $date) {
            if (!$expected->isSameDay(\Carbon\Carbon::parse($date))) {
                throw new Exception('Calendar-based leaves must be continuous.');
            }
            $expected->addDay();
        }
    }

    private function validateOverlap($employee, $leaveDates)
    {
        $dates = $leaveDates->pluck('date')->toArray();

        $conflicts = LeaveApplicationDate::whereIn('date', $dates)
            ->whereHas('leaveApplication', function ($q) use ($employee) {
                $q->where('employee_id', $employee->id)
                    ->whereHas('currentStatus', function ($q2) {
                        $q2->whereNotIn('status', ['cancelled', 'approved', 'disapproved']);
                    });
            })
            ->pluck('date')
            ->toArray();

        if (!empty($conflicts)) {
            $formatted = implode(', ', array_map(
                fn($d) => \Carbon\Carbon::parse($d)->format('M d, Y'),
                $conflicts
            ));

            throw new Exception("You have already applied leave on: $formatted.");
        }
    }

    private function computeCredits($leaveDates, $leaveName, $employee)
    {
        $isCalendar = $this->isCalendarBasedLeave($leaveName);

        // =========================
        // HOLIDAYS
        // =========================
        $holidays = Holiday::pluck('date')->toArray();

        // =========================
        // EMPLOYEE WORK SCHEDULE
        // IMPORTANT: NO DEFAULT WEEKDAYS FALLBACK
        // =========================
        $scheduleWorkDays = collect($employee->weeklyShiftTemplate?->weekly_shift_days ?? []);

        // =========================
        // FILTER VALID DATES
        // =========================
        $validDates = collect($leaveDates)
            ->filter(fn($d) => $d['included'] ?? true)

            // exclude holidays
            ->reject(fn($d) => in_array($d['date'], $holidays))

            // exclude non-working days ONLY if schedule exists
            ->when(!$isCalendar && $scheduleWorkDays->isNotEmpty(), function ($collection) use ($scheduleWorkDays) {
                return $collection->filter(function ($d) use ($scheduleWorkDays) {
                    $dayName = Carbon::parse($d['date'])->format('l');
                    return $scheduleWorkDays->contains($dayName);
                });
            })
            ->values();

        // =========================
        // CALENDAR VALIDATION
        // =========================
        if ($isCalendar) {
            $this->validateContinuousDates($validDates);
        }

        // =========================
        // COMPUTE CREDITS
        // =========================
        $credits = $validDates->sum(function ($d) use ($isCalendar) {

            // Calendar-based leaves = whole day only
            if ($isCalendar) {
                return 1;
            }

            // Regular leaves: allow half-day only for CTO logic if needed later
            return in_array($d['duration'] ?? 'full_day', ['half_day_am', 'half_day_pm'])
                ? 0.5
                : 1;
        });

        return [
            'credits' => $credits,
            'validDates' => $validDates,
        ];
    }


    private function validateCredits($employee, $leave, $credits)
    {
        $latest = EmployeeLeaveCreditsHistory::where('employee_id', $employee->id)
            ->where('leave_id', $leave->id)
            ->latest()
            ->first();

        $allowedUnpaid = ['Vacation Leave', 'Sick Leave'];

        $hasEnough = $latest && $latest->balance >= $credits;

        if (!$hasEnough && !in_array($leave->name, $allowedUnpaid)) {
            throw new Exception('You do not have enough leave credits.');
        }
    }

    private function createLeaveApplication($employee, $leave, $leaveDates, $credits, $validated)
    {
        return LeaveApplication::create([
            'employee_id' => $employee->id,
            'leave_id' => $leave->id,
            'location_within_philippines' => $validated['location_within_philippines'] ?? null,
            'location_abroad' => $validated['location_abroad'] ?? null,
            'in_hospital' => $validated['in_hospital'] ?? null,
            'out_hospital' => $validated['out_hospital'] ?? null,
            'illness' => $validated['illness'] ?? null,
            'sick_leave_type' => $validated['sickLeaveType'] ?? null,
            'study_leave_application' => $validated['study_leave_application'] ?? null,
            'other_purposes' => $validated['otherPurpose'] ?? null,
            'no_of_days' => $credits,
            'from' => $leaveDates->min('date'),
            'to' => $leaveDates->max('date'),
            'commutation' => $validated['commutation'] ?? null,
            'credits' => $credits,
        ]);
    }

    private function saveLeaveDates($leaveApplication, $leaveDates)
    {
        foreach ($leaveDates as $d) {
            $leaveApplication->leaveDates()->create([
                'date' => $d['date'],
                'duration' => $d['duration'] ?? 'full_day',
                'credits' => in_array($d['duration'] ?? 'full_day', ['half_day_am', 'half_day_pm']) ? 0.5 : 1,
            ]);
        }
    }

    private function setInitialStatus($leaveApplication)
    {
        $status = $leaveApplication->leaveStatuses()->create([
            'status' => 'pending',
            'acted_by' => null,
            'acted_at' => now(),
        ]);

        $leaveApplication->update([
            'current_status_id' => $status->id,
        ]);
    }

    private function notifySupervisor($employee, $leaveApplication)
    {
        if ($employee->immediateSupervisor?->id) {
            $employee->immediateSupervisor->notify(
                new LeaveApplicationNotification(
                    $leaveApplication,
                    $employee->personalInformation,
                    $employee->immediateSupervisor
                )
            );
        }
    }

    /* Leave Applications on Special Leave Types e.g. COC */

    public function storeLeaveApplicationOthers(array $validated)
    {
        $employee = Employee::with('personalInformation', 'immediateSupervisor', 'weeklyShiftTemplate')
            ->find(auth()->user()->employee_id);

        $personalInfo = $employee->personalInformation;

        // ✅ Work schedule fallback
        $scheduleWorkDays = collect(
            $employee->weeklyShiftTemplate?->weekly_shift_days ?? []
        )->whenEmpty(fn() => collect([
                'Monday',
                'Tuesday',
                'Wednesday',
                'Thursday',
                'Friday',
            ]));

        $leave = Leave::where('name', $validated['leaveType'])->firstOrFail();
        $specialLeaveName = strtolower(
            EmployeeLeaveCreditsHistory::with('specialLeave')
                ->find($validated['specialLeaveCreditId'])
                ->specialLeave
                ->name ?? ''
        );

        $isCto = strtolower($specialLeaveName) === 'compensatory overtime credit';


        // --- Handle leave_dates ---
        $leaveDates = collect($validated['leave_dates'])
            ->filter(fn($d) => $d['included'] ?? true)
            ->filter(function ($d) use ($scheduleWorkDays, $isCto) {

                // 👉 CTO ignores strict schedule filtering (important)
                if ($isCto) {
                    return true;
                }

                $dayName = Carbon::parse($d['date'])->format('l');
                return $scheduleWorkDays->contains($dayName);
            })
            ->values();

        if ($leaveDates->isEmpty()) {
            throw new Exception('Please select at least one valid leave date.');
        }

        $dates = $leaveDates->pluck('date')->toArray();

        // --- Overlap check ---
        if ($isCto) {

            // CTO: check date + duration (slot-based)
            foreach ($leaveDates as $d) {

                $exists = LeaveApplicationDate::where('date', $d['date'])
                    ->where('duration', $d['duration'])
                    ->whereHas('leaveApplication', function ($q) use ($employee) {
                        $q->where('employee_id', $employee->id)
                            ->whereHas('currentStatus', function ($q2) {
                                $q2->whereNotIn('status', ['cancelled', 'disapproved']);
                            });
                    })
                    ->exists();

                if ($exists) {
                    throw new Exception(
                        "You already have a CTO application for {$d['date']} ({$d['duration']})."
                    );
                }
            }

        } else {

            // NORMAL: date-only check
            $conflictingDates = LeaveApplicationDate::whereIn('date', $dates)
                ->whereHas('leaveApplication', function ($q) use ($employee) {
                    $q->where('employee_id', $employee->id)
                        ->whereHas('currentStatus', function ($q2) {
                            $q2->whereNotIn('status', ['cancelled', 'disapproved']);
                        });
                })
                ->pluck('date')
                ->toArray();

            if (!empty($conflictingDates)) {
                $formattedDates = implode(', ', array_map(
                    fn($d) => Carbon::parse($d)->format('M d, Y'),
                    $conflictingDates
                ));

                throw new Exception("You have already applied leave on: $formattedDates.");
            }
        }

        // $conflictingDates = LeaveApplicationDate::whereIn('date', $dates)
        //     ->whereHas(
        //         'leaveApplication',
        //         fn($q) =>
        //         $q->where('employee_id', $employee->id)
        //             ->whereHas(
        //                 'currentStatus',
        //                 fn($q2) =>
        //                 $q2->whereNotIn('status', ['cancelled', 'approved', 'disapproved'])
        //             )
        //     )
        //     ->pluck('date')
        //     ->toArray();

        // if (!empty($conflictingDates)) {
        //     $formattedDates = implode(', ', array_map(
        //         fn($d) => Carbon::parse($d)->format('M d, Y'),
        //         $conflictingDates
        //     ));

        //     throw new Exception("You have already applied leave on the following date(s): $formattedDates.");
        // }

        // --- Compute credits ---
        $credits = $leaveDates->sum(
            fn($d) =>
            in_array($d['duration'], ['half_day_am', 'half_day_pm']) ? 0.5 : 1
        );

        $leaveApplication = null;

        try {
            DB::transaction(function () use ($employee, $leave, $leaveDates, $credits, $personalInfo, $validated, &$leaveApplication, $isCto) {

                // --- Create Leave Application ---
                $leaveApplication = LeaveApplication::create([
                    'employee_id' => $employee->id,
                    'leave_id' => $leave->id,
                    'special_leave_credit_id' => $validated['specialLeaveCreditId'] ?? null,
                    'location_type' => $validated['locationType'] ?? null,
                    'location_within_philippines' => $validated['location_within_philippines'] ?? null,
                    'location_abroad' => $validated['location_abroad'] ?? null,
                    'in_hospital' => $validated['in_hospital'] ?? null,
                    'out_hospital' => $validated['out_hospital'] ?? null,
                    'illness' => $validated['illness'] ?? null,
                    'from' => $leaveDates->min('date'),
                    'to' => $leaveDates->max('date'),
                    'no_of_days' => $isCto
                        ? $leaveDates->sum(fn($d) => $d['duration'] === 'full_day' ? 1 : 0.5)
                        : $leaveDates->count(),
                    'commutation' => $validated['commutation'] ?? null,
                    'credits' => $credits,
                ]);

                // --- Save leave dates ---
                foreach ($leaveDates as $d) {
                    $leaveApplication->leaveDates()->create([
                        'date' => $d['date'],
                        'duration' => $d['duration'] ?? 'full_day',
                        'credits' => in_array($d['duration'] ?? 'full_day', ['half_day_am', 'half_day_pm']) ? 0.5 : 1,
                    ]);
                }

                // --- Create initial status ---
                $initialStatus = $leaveApplication->leaveStatuses()->create([
                    'status' => 'pending',
                    'acted_by' => null,
                    'acted_at' => now(),
                    'remarks' => null,
                ]);

                $leaveApplication->update([
                    'current_status_id' => $initialStatus->id,
                ]);

                // ✅ IMPORTANT: Deduct leave credits
                $this->deduct($leaveApplication);

                // --- Notify supervisor ---
                if ($employee->immediateSupervisor?->id) {
                    $employee->immediateSupervisor->notify(
                        new LeaveApplicationNotification(
                            $leaveApplication,
                            $personalInfo,
                            $employee->immediateSupervisor
                        )
                    );
                }
            });
        } catch (\Exception $e) {
            report($e);
            throw new \Exception('Failed to file leave application.', 0, $e);
        }

        return $leaveApplication;
    }

    public function deduct($employeeLeave, $leaveApplication = null)
    {
        return DB::transaction(function () use ($employeeLeave, $leaveApplication) {

            $employee = $employeeLeave->employee()->with('employeeDesignations')->first();

            $employeeId = $employeeLeave->employee_id;
            $leaveId = $employeeLeave->leave_id;
            $credits = $employeeLeave->credits;

            $isTeaching = $employee->employee_type === 'Teaching';
            $hasDesignation = $employee->employeeDesignations()->exists();

            // 🔍 Get Service Credit Leave ID
            $serviceCreditId = optional(
                Leave::where('name', 'Service Credits')->first()
            )->id;

            switch ($leaveId) {

                case 1: // Vacation Leave
                case 3: // Sick Leave

                    // ============================================
                    // 🔥 SPECIAL RULE FOR TEACHING EMPLOYEES
                    // ============================================
                    $targetLeaveId = $leaveId;

                    // 🔥 Teaching without designation uses Service Credit FIRST
                    if ($isTeaching && !$hasDesignation && $serviceCreditId) {
                        $targetLeaveId = $serviceCreditId;
                    }

                    $this->deductFromLeave($employeeId, $targetLeaveId, $credits, $leaveApplication);
                    break;

                case 4: // Maternity Leave
                case 5: // Paternity Leave
                case 6: // Special Privilege Leave
                case 7: // Solo Parent Leave
                case 8: // Study Leave
                case 9: // Rehabilitation Leave
                case 10: // Special Leave for Women
                case 11: // Calamity Leave
                case 12: // Adoption Leave

                    $this->deductFromLeave($employeeId, $leaveId, $credits, $leaveApplication);
                    break;

                case 2: // Mandatory / Forced Leave

                    // Deduct from Vacation Leave
                    $this->deductFromLeave($employeeId, 1, $credits, $leaveApplication);

                    // Deduct from Forced Leave
                    $this->deductFromLeave($employeeId, 2, $credits, $leaveApplication);

                    break;

                case 13: // Others (Special Leave)

                    $this->deductSpecialLeave($employeeLeave, $leaveApplication);
                    break;

                default:
                    throw new Exception('Invalid leave type.');
            }
        });
    }

    // private function deductFromLeave($employeeId, $leaveId, $credits)
    // {
    //     $latest = EmployeeLeaveCreditsHistory::where('employee_id', $employeeId)
    //         ->where('leave_id', $leaveId)
    //         ->latest()
    //         ->first();

    //     if (!$latest) {
    //         throw new Exception("No leave credits found for leave ID {$leaveId}.");
    //     }

    //     $leave = Leave::findOrFail($leaveId);
    //     $leaveName = $leave->name;

    //     $currentBalance = (float) $latest->balance;
    //     $requested = (float) $credits;

    //     $isVLorSL = in_array($leaveId, [1, 3]);

    //     // ---------------------------------------------------
    //     // 🔥 CORE RULE: compute paid days as integer only
    //     // ---------------------------------------------------
    //     $paid = min(
    //         (int) floor($currentBalance),
    //         (int) floor($requested)
    //     );

    //     $unpaid = $requested - $paid;

    //     // ---------------------------------------------------
    //     // ❗ VALIDATION RULES
    //     // ---------------------------------------------------
    //     if (!$isVLorSL && $paid < $requested) {
    //         throw new Exception("Insufficient leave credits for {$leaveName}.");
    //     }

    //     // ---------------------------------------------------
    //     // 💾 CREATE LEDGER ENTRY
    //     // ---------------------------------------------------
    //     return EmployeeLeaveCreditsHistory::create([
    //         'employee_id' => $employeeId,
    //         'leave_id' => $leaveId,
    //         'total_earned' => $currentBalance,
    //         'credit_addition' => 0,

    //         // ONLY PAID PORTION IS DEDUCTED
    //         'credit_deduction' => $paid,

    //         // BALANCE NEVER USES FLOAT DIRECTLY
    //         'balance' => $currentBalance - $paid,
    //     ]);
    // }


    private function deductFromLeave($employeeId, $leaveId, $credits, $leaveApplication = null)
    {
        $leave = Leave::findOrFail($leaveId);
        $leaveName = $leave->name;

        $requested = (float) $credits;

        // ----------------------------------------
        // 🔥 RULE: which leaves allow unpaid
        // ----------------------------------------
        $allowUnpaid = in_array($leaveId, [1, 3]) // VL, SL
            || strtolower($leaveName) === 'service credits';

        // ----------------------------------------
        // 🔍 GET LATEST BALANCE (default = 0)
        // ----------------------------------------
        $latest = EmployeeLeaveCreditsHistory::where('employee_id', $employeeId)
            ->where('leave_id', $leaveId)
            ->latest()
            ->first();

        $currentBalance = $latest ? (float) $latest->balance : 0.0;

        // ----------------------------------------
        // 🔢 COMPUTE PAID VS UNPAID
        // ----------------------------------------
        $paid = min(
            (int) floor($currentBalance),
            (int) floor($requested)
        );

        $unpaid = $requested - $paid;

        // ----------------------------------------
        // ❗ VALIDATION (STRICT LEAVES ONLY)
        // ----------------------------------------
        if (!$allowUnpaid && $currentBalance < $requested) {
            throw new Exception("Insufficient leave credits for {$leaveName}.");
        }

        // ----------------------------------------
        // 💾 CREATE LEDGER ENTRY
        // ----------------------------------------
        return EmployeeLeaveCreditsHistory::create([
            'employee_id' => $employeeId,
            'leave_id' => $leaveId,
            'total_earned' => $currentBalance,
            'credit_addition' => 0,
            'credit_deduction' => $paid,
            'balance' => $currentBalance - $paid,
            'origin' => EmployeeLeaveCreditsHistory::ORIGIN_LEAVE_APPLICATION,
            'remarks' => $leaveApplication ? "Deducted for leave application (Leave ID: {$leaveApplication->id})" : null,
        ]);
    }

    /**
     * Special leave (Others)
     */
    private function deductSpecialLeave($employeeLeave, $leaveApplication = null)
    {
        $employeeId = $employeeLeave->employee_id;
        $credits = $employeeLeave->credits;
        $documentTypeNumber = $employeeLeave->specialLeaveCredit->document_type_number;

        $latest = EmployeeLeaveCreditsHistory::where('employee_id', $employeeId)
            ->where('document_type_number', $documentTypeNumber)
            ->latest()
            ->first();

        if (!$latest) {
            throw new Exception('No previous credit history found for this special leave.');
        }

        if ($latest->balance < $credits) {
            throw new Exception('Insufficient special leave credits.');
        }

        return EmployeeLeaveCreditsHistory::create([
            'employee_id' => $employeeId,
            'leave_id' => $employeeLeave->leave_id,
            'total_earned' => $latest->balance,
            'credit_addition' => 0,
            'credit_deduction' => $credits,
            'balance' => $latest->balance - $credits,
            'special_leave_id' => $latest->special_leave_id,
            'document_type_number' => $latest->document_type_number,
            'expiration_date_from' => $latest->expiration_date_from,
            'expiration_date_to' => $latest->expiration_date_to,
            'origin' => EmployeeLeaveCreditsHistory::ORIGIN_LEAVE_APPLICATION,
            'remarks' => $leaveApplication ? "Deducted for special leave application (Leave ID: {$leaveApplication->id})" : null,
        ]);
    }

    public function cancelLeaveApplication(array $validated)
    {
        $leaveApplication = LeaveApplication::where('id', $validated['id'])->first();
        if ($leaveApplication->status == 'approved') {
            throw new Exception('Approved leave applications cannot be cancelled. Please contact HRMO for assistance.');
        } else {

            $leaveApplication->update([
                'status' => 'cancelled',
                'cancellation_reason' => $validated['reason'],
            ]);
        }


        return $leaveApplication;
    }

    public function editLeaveApplication(string $id)
    {
        $leaveApplication = LeaveApplication::with('employee.personalInformation', 'leave')
            ->where('id', $id)
            ->first();

        return $leaveApplication;
    }
}

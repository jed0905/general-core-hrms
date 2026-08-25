<?php

namespace App\Http\Requests;

use App\Models\EmployeeLeaveCreditsHistory;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeaveApplicationFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    private function getWorkingDays(?int $employeeId = null): array
    {
        $employee = $employeeId
            ? \App\Models\Employee::find($employeeId)
            : optional($this->user())->employee;

        if (!$employee) {
            return [];
        }

        return $employee->dailyShiftSchedules()
            ->where('status', 'active')
            ->pluck('day_of_week')
            ->map(fn($d) => strtolower($d))
            ->toArray();
    }

    private function isWorkingDay(Carbon $date, array $workDays): bool
    {
        return in_array(strtolower($date->format('l')), $workDays);
    }

    protected function prepareForValidation()
    {
        $this->replace(
            collect($this->all())
                ->except([
                    'isDirty',
                    'errors',
                    'hasErrors',
                    'processing',
                    'progress',
                    'wasSuccessful',
                    'recentlySuccessful',
                    '__rememberable',
                ])
                ->toArray()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date', 'before_or_equal:to'],
            'to' => ['required', 'date', 'after_or_equal:from'],

            'leaveType' => [
                'required',
                Rule::in($this->leaveTypes()),
            ],

            'leave_dates' => ['array'],
            'leave_dates.*.date' => ['required', 'date'],
            'leave_dates.*.duration' => ['required', Rule::in(['full_day', 'half_day_am', 'half_day_pm'])],

            'location' => ['nullable', 'string', 'max:255'],
            'location_within_philippines' => ['nullable', 'string', 'max:255'],
            'location_abroad' => ['nullable', 'string', 'max:255'],

            'commutation' => ['required', 'string', 'max:255'],

            'in_hospital' => ['boolean', 'max:255', 'nullable'],
            'out_hospital' => ['boolean', 'max:255', 'nullable'],
            'illness' => ['nullable', 'string', 'max:255'],
            'study_leave_application' => ['nullable', 'string', 'max:255'],

            'specialLeaveBenefitsForWomenIllness' => ['nullable', 'string', 'max:255'],

            'specialLeaveCreditId' => ['nullable', 'exists:employee_leave_credits_histories,id'],
            'late_filing_reason' => ['nullable', 'string', 'max:500'],

        ];
    }
    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'leave_dates.required' => 'Please select leave dates.',
            'leave_dates.*.date.required' => 'Each leave date must have a valid date.',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {

            $config = $this->leaveRulesMap()[$this->leaveType] ?? [];

            // --- SAME MONTH VALIDATION (only if NOT abroad AND not allowed multi-month) ---
            $allowMultiMonth = $config['allow_multi_month'] ?? false;


            if (
                $this->from &&
                $this->to &&
                $this->location !== 'abroad' &&
                !$allowMultiMonth
            ) {
                $from = Carbon::parse($this->from);
                $to = Carbon::parse($this->to);

                if (!$from->isSameMonth($to)) {
                    $validator->errors()->add(
                        'to',
                        'Leave dates must fall within the same month unless the location is abroad. Please submit separate applications for dates spanning different months.'
                    );
                    return;
                }
            }


            $leaveDates = collect($this->leave_dates);

            $isCto = false;

            if ($this->leaveType === 'Others') {
                $leaveCredit = EmployeeLeaveCreditsHistory::with('specialLeave')
                    ->find($this->specialLeaveCreditId);

                $specialName = strtolower($leaveCredit->specialLeave->name ?? '');

                $isCto = $specialName === 'compensatory overtime credit';
            }

            if ($leaveDates->isEmpty()) {
                $validator->errors()->add('leave_dates', 'Please select at least one leave date.');
                return;
            }

            // --- Compute total days ---
            $days = $leaveDates->sum(
                fn($d) =>
                in_array($d['duration'], ['half_day_am', 'half_day_pm']) ? 0.5 : 1
            );

            if ($days <= 0) {
                $validator->errors()->add('leave_dates', 'The number of working days must be greater than zero.');
                return;
            }

            if (isset($config['fixed_days'])) {
                if ($days != $config['fixed_days']) {
                    $validator->errors()->add(
                        'leave_dates',
                        "{$this->leaveType} must be exactly {$config['fixed_days']} days."
                    );
                }
            }

            // --- BASIC REQUIRED FIELDS ---
            foreach ($config['requires'] ?? [] as $field) {
                if (empty($this->$field)) {
                    $validator->errors()->add($field, "Please specify {$field}.");
                }
            }

            // --- LOCATION BASED ---
            if (($config['location_based'] ?? false)) {

                if (empty($this->location)) {
                    $validator->errors()->add('location', 'Please specify location.');
                }

                if ($this->location === 'within' && empty($this->location_within_philippines)) {
                    $validator->errors()->add('location_within_philippines', 'Please specify location within the Philippines.');
                }

                if ($this->location === 'abroad' && empty($this->location_abroad)) {
                    $validator->errors()->add('location_abroad', 'Please specify location abroad.');
                }
            }

            // --- ADVANCE FILING ---
            if (isset($config['advance_days']) && $this->from) {

                $requiredDate = $config['type'] === 'working'
                    ? now()->addWeekdays($config['advance_days'])
                    : now()->addDays($config['advance_days']);

                if (Carbon::parse($this->from)->lt($requiredDate)) {
                    $validator->errors()->add(
                        'from',
                        "{$this->leaveType} must be filed at least {$config['advance_days']} {$config['type']} days in advance."
                    );
                }
            }

            // --- SICK LEAVE TYPE VALIDATION ---
            if (in_array($this->leaveType, ['Sick Leave', 'Rehabilitation Privilege'])) {

                if (!in_array($this->sickLeaveType, ['hospital', 'outPatient'])) {
                    $validator->errors()->add('sickLeaveType', 'Please select Hospital or Outpatient.');
                }
            }

            // if ($this->leaveType === 'Sick Leave') {

            //     $employeeId = $this->input('employee_id') ?? optional($this->user())->employee_id;
            //     $workDays = $this->getWorkingDays($employeeId);

            //     $to = Carbon::parse($this->to)->startOfDay(); // last leave day
            //     $filingDate = now()->startOfDay();

            //     // =========================
            //     // 1. GET NEXT WORKING DAY (RETURN DATE)
            //     // =========================
            //     $returnDate = $to->copy();

            //     do {
            //         $returnDate->addDay();
            //     } while (!$this->isWorkingDay($returnDate, $workDays));

            //     // =========================
            //     // 2. COUNT WORKING DAYS FROM RETURN → FILING DATE
            //     // =========================
            //     $daysAfterReturn = 0;
            //     $cursor = $returnDate->copy();

            //     while ($cursor->lt($filingDate)) {

            //         if ($this->isWorkingDay($cursor, $workDays)) {
            //             $daysAfterReturn++;
            //         }

            //         $cursor->addDay();
            //     }

            //     // =========================
            //     // 3. VALIDATION
            //     // =========================
            //     if ($daysAfterReturn >= 5 && empty($this->late_filing_reason)) {
            //         $validator->errors()->add(
            //             'late_filing_reason',
            //             'Late filing reason is required for sick leave filed 5 working days or more after return.'
            //         );
            //     }
            // }

            // =====================================================
            // 🔥 MAX CONSECUTIVE DAYS (GLOBAL + OTHERS)
            // =====================================================

            // Get included dates (safe even if 'included' not present)
            // $includedDates = $leaveDates
            //     ->filter(fn($d) => $d['included'] ?? true)
            //     ->pluck('date')
            //     ->map(fn($d) => Carbon::parse($d))
            //     ->sort()
            //     ->values();

            $groupedByDate = $leaveDates
                ->filter(fn($d) => $d['included'] ?? true)
                ->groupBy('date');

            // ❗ NON-CTO: collapse same-day entries (fixes AM + PM issue)
            if (!$isCto) {
                $includedDates = $groupedByDate
                    ->keys()
                    ->map(fn($d) => Carbon::parse($d))
                    ->sort()
                    ->values();
            } else {
                // CTO: allow same-day entries but streak logic still uses unique dates
                $includedDates = $groupedByDate
                    ->keys()
                    ->map(fn($d) => Carbon::parse($d))
                    ->sort()
                    ->values();
            }

            if (!$isCto) {
                $hasDuplicateDates = $leaveDates
                    ->groupBy('date')
                    ->contains(fn($g) => $g->count() > 1);

                if ($hasDuplicateDates) {
                    $validator->errors()->add(
                        'leave_dates',
                        'Only Compensatory Overtime Credit allows multiple entries for the same date (AM/PM).'
                    );
                }
            }

            // =========================
            // HOLIDAYS + UNIVERSITY ACTIVITIES VALIDATION
            // =========================

            // Holidays (dates only)
            $holidays = \App\Models\Holiday::pluck('date')
                ->map(fn($d) => Carbon::parse($d)->toDateString())
                ->toArray();

            // =========================
            // VALIDATION CHECK
            // =========================

            foreach ($includedDates as $date) {
                $d = $date->toDateString();

                if (in_array($d, $holidays)) {
                    $validator->errors()->add(
                        'leave_dates',
                        "Leave cannot be filed on a holiday ({$d})."
                    );
                }
            }

            // =========================
            // HOLIDAYS + UNIVERSITY ACTIVITIES VALIDATION
            // =========================

            // Holidays (dates only)
            $holidays = \App\Models\Holiday::pluck('date')
                ->map(fn($d) => Carbon::parse($d)->toDateString())
                ->toArray();

            // =========================
            // VALIDATION CHECK
            // =========================

            foreach ($includedDates as $date) {
                $d = $date->toDateString();

                if (in_array($d, $holidays)) {
                    $validator->errors()->add(
                        'leave_dates',
                        "Leave cannot be filed on a holiday ({$d})."
                    );
                }
            }

            $maxStreak = 0;
            $currentStreak = 0;

            for ($i = 0; $i < $includedDates->count(); $i++) {
                if ($i === 0) {
                    $currentStreak = 1;
                } else {
                    $diff = $includedDates[$i - 1]->diffInDays($includedDates[$i]);

                    if ($diff === 1) {
                        $currentStreak++;
                    } else {
                        $currentStreak = 1;
                    }
                }

                $maxStreak = max($maxStreak, $currentStreak);
            }

            // --- APPLY GLOBAL MAX (from leaveRulesMap) ---
            if (isset($config['max_consecutive_days']) && $maxStreak > $config['max_consecutive_days']) {
                $validator->errors()->add(
                    'leave_dates',
                    "{$this->leaveType} cannot exceed {$config['max_consecutive_days']} consecutive days."
                );
            }

            // --- OTHERS (SPECIAL CASE) ---
            if ($this->leaveType === 'Others') {

                if (!$this->specialLeaveCreditId) {
                    $validator->errors()->add('specialLeaveCreditId', 'Please select a special leave type.');
                    return;
                }

                $leaveCredit = EmployeeLeaveCreditsHistory::with('specialLeave')
                    ->find($this->specialLeaveCreditId);

                $specialName = strtolower($leaveCredit->specialLeave->name ?? '');

                // dd($maxStreak);

                // --- CTO ---
                if ($specialName === 'compensatory overtime credit') {

                    $expirationDate = Carbon::parse($leaveCredit->expiration_date_to)->endOfDay();

                    foreach ($leaveDates as $leaveDate) {

                        $date = Carbon::parse($leaveDate['date']);

                        if ($date->gt($expirationDate)) {
                            $validator->errors()->add(
                                'leave_dates',
                                "Selected date {$date->toDateString()} exceeds the expiration of your compensatory overtime credit ({$expirationDate->toDateString()})."
                            );
                        }
                    }

                    $requiredDate = now()->startOfDay()->addDays(1);

                    if (Carbon::parse($this->from)->startOfDay()->lt($requiredDate)) {
                        $validator->errors()->add(
                            'from',
                            'Compensatory Overtime Credit must be filed at least 1 day in advance.'
                        );
                    }

                    // ✅ REQUIRE LOCATION
                    if (empty($this->location)) {
                        $validator->errors()->add('location', 'Please specify location.');
                    }

                    if ($this->location === 'within' && empty($this->location_within_philippines)) {
                        $validator->errors()->add(
                            'location_within_philippines',
                            'Please specify location within the Philippines.'
                        );
                    }

                    if ($this->location === 'abroad' && empty($this->location_abroad)) {
                        $validator->errors()->add(
                            'location_abroad',
                            'Please specify location abroad.'
                        );
                    }
                }

                // --- MAX CONSECUTIVE FOR SPECIAL LEAVES ---
                $specialMax = [
                    'compensatory overtime credit' => 5,
                    'wellness leave' => 3,
                ];

                if (isset($specialMax[$specialName]) && $maxStreak > $specialMax[$specialName]) {
                    $validator->errors()->add(
                        'leave_dates',
                        ucfirst($specialName) . " cannot exceed {$specialMax[$specialName]} consecutive days."
                    );
                }


                if ($specialName === 'wellness leave') {

                    $employeeId = $this->input('employee_id') ?? optional($this->user())->employee_id;
                    $workDays = $this->getWorkingDays($employeeId);

                    $to = Carbon::parse($this->to)->startOfDay(); // last leave day
                    $filingDate = now()->startOfDay(); // 👈 ACTUAL submission date

                    // =========================
                    // CASE 1: ADVANCE FILING
                    // =========================
                    $firstAllowedDate = $to->copy()->subDays(5);

                    if ($filingDate->lte($firstAllowedDate)) {
                        return; // valid advance filing
                    }

                    // =========================
                    // CASE 2: POST-RETURN FILING
                    // =========================
                    // $nextWorkingDay = $to->copy();

                    // do {
                    //     $nextWorkingDay->addDay();
                    // } while (!in_array(strtolower($nextWorkingDay->format('l')), $workDays));

                    // if (!$filingDate->isSameDay($nextWorkingDay)) {
                    //     $validator->errors()->add(
                    //         'from',
                    //         'Wellness leave must be filed on the next working day after return.'
                    //     );
                    // }

                    // =====================================
                    // CASE 2: POST-RETURN FILING
                    // =====================================

                    $biometricIds = \App\Models\EmployeeBiometricId::where('employee_id', $employeeId)
                        ->pluck('biometric_id');


                    $returnDate = $to->copy()->addDay();

                    $firstReportingDay = null;

                    while ($returnDate->lte($filingDate)) {

                        // Skip non-working days
                        if (!$this->isWorkingDay($returnDate, $workDays)) {
                            $returnDate->addDay();
                            continue;
                        }

                        // Skip holidays
                        if (\App\Models\Holiday::whereDate('date', $returnDate)->exists()) {
                            $returnDate->addDay();
                            continue;
                        }

                        $hasAttendance = \App\Models\EmployeeAttendanceLog::whereIn(
                            'employee_id',   // contains biometric IDs
                            $biometricIds
                        )
                            ->where('auth_date', $returnDate->toDateString())
                            ->exists();

                        if ($hasAttendance) {
                            $firstReportingDay = $returnDate->copy();
                            break;
                        }

                        $returnDate->addDay();
                    }

                    if ($firstReportingDay && !$filingDate->isSameDay($firstReportingDay)) {
                        $validator->errors()->add(
                            'from',
                            'Wellness leave must be filed on the first reporting day after returning to work.'
                        );
                    }

                    // if (!$filingDate->isSameDay($returnDate)) {
                    //     $validator->errors()->add(
                    //         'from',
                    //         'Wellness leave must be filed on the first reporting day after returning to work.'
                    //     );
                    // }
                }


            }
        });
    }

    private function leaveTypes(): array
    {
        return [
            'Vacation Leave',
            'Mandatory/Forced Leave',
            'Sick Leave',
            'Maternity Leave',
            'Paternity Leave',
            'Special Privilege Leave',
            'Solo Parent Leave',
            'Study Leave',
            'Rehabilitation Privilege',
            'Special Leave for Benefits for Women',
            'Special Emergency (Calamity) Leave',
            'Adoption Leave',
            'Others',
        ];
    }

    private function leaveRulesMap(): array
    {
        return [
            'Vacation Leave' => [
                'location_based' => true,
                'advance_days' => 5,
                'type' => 'calendar',
                'allow_multi_month' => false
            ],
            'Mandatory/Forced Leave' => [
                'location_based' => true,
                'advance_days' => 5,
                'type' => 'calendar',
                'allow_multi_month' => false
            ],
            'Sick Leave' => [
                'requires' => ['illness', 'sickLeaveType'],
                'allow_multi_month' => false
            ],
            'Maternity Leave' => [
                'fixed_days' => 105,
                'allow_multi_month' => true
            ],
            'Special Privilege Leave' => [
                'location_based' => true,
                // 'advance_days' => 5,
                // 'type' => 'calendar',
                'max_consecutive_days' => 3,
                'allow_multi_month' => false
            ],
            'Solo Parent Leave' => [
                'location_based' => true,
                'advance_days' => 5,
                'allow_multi_month' => false
            ],
            'Study Leave' => [
                'requires' => ['study_leave_application'],
                'allow_multi_month' => true
            ],
            'Rehabilitation Privilege' => [
                'requires' => ['illness', 'sickLeaveType'],
                'advance_days' => 7,
                'allow_multi_month' => false
            ],
            'Special Leave for Women (RA 9710)' => [
                'requires' => ['specialLeaveBenefitsForWomen'],
                'allow_multi_month' => false
            ],
            'Special Emergency (Calamity) Leave' => [
                'location_based' => true,
                'allow_multi_month' => false
            ],
            'Others' => [
                'requires' => ['specialLeaveCreditId'],
                'allow_multi_month' => false
            ],
        ];
    }

}

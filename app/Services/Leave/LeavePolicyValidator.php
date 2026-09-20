<?php

namespace App\Services\Leave;

use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveApplicationDate;
use App\Models\LeavePolicyRule;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class LeavePolicyValidator
{
    /**
     * Validate requested leave against active policy rules.
     * Returns the matching LeavePolicyRule if found.
     */
    public function validate(int $employeeId, int $leaveTypeId, array $dates, array $files = []): ?LeavePolicyRule
    {
        $employee = Employee::findOrFail($employeeId);
        $today = now()->toDateString();

        // 1. Fetch active Leave Policy and matching Rule
        $rule = LeavePolicyRule::whereHas('policy', function ($q) use ($today) {
            $q->where('is_active', true)
                ->where('effective_from', '<=', $today)
                ->where(function ($sub) use ($today) {
                    $sub->whereNull('effective_to')
                        ->orWhere('effective_to', '>=', $today);
                });
        })
            ->where('leave_type_id', $leaveTypeId)
            ->where('is_active', true)
            ->first();

        // 2. Prevent Overlapping Leave Dates
        $requestedDates = collect($dates)->pluck('leave_date')->filter()->toArray();
        if (!empty($requestedDates)) {
            $hasOverlap = LeaveApplicationDate::whereHas('application', function ($q) use ($employeeId) {
                $q->where('employee_id', $employeeId)
                    ->whereIn('status', ['pending', 'approved']);
            })->whereIn('leave_date', $requestedDates)->exists();

            if ($hasOverlap) {
                throw ValidationException::withMessages([
                    'dates' => ['You already have a pending or approved leave request on one of the selected dates.']
                ]);
            }
        }

        if (!$rule) {
            return null; // No active policy rule bound to this leave type
        }

        $hireDate = $employee->date_hired ? Carbon::parse($employee->date_hired) : $employee->created_at;

        // 3. Minimum Service Months Check
        if ($rule->minimum_service_months > 0) {
            $serviceMonths = $hireDate->diffInMonths(now());
            if ($serviceMonths < $rule->minimum_service_months) {
                throw ValidationException::withMessages([
                    'leave_type_id' => ["Minimum service of {$rule->minimum_service_months} month(s) required for this leave."]
                ]);
            }
        }

        // 4. Waiting Period Check (Days)
        if ($rule->waiting_period > 0) {
            $daysEmployed = $hireDate->diffInDays(now());
            if ($daysEmployed < $rule->waiting_period) {
                throw ValidationException::withMessages([
                    'leave_type_id' => ["You must complete a waiting period of {$rule->waiting_period} day(s) before applying for this leave."]
                ]);
            }
        }

        // 5. Duration Unit Restrictions (Half-Day / Hourly)
        foreach ($dates as $dateRow) {
            $durationType = $dateRow['duration_type'] ?? 'full_day';

            if ($durationType === 'half_day' && !$rule->allows_half_day) {
                throw ValidationException::withMessages([
                    'dates' => ['Half-day leave requests are not permitted for this leave type.']
                ]);
            }

            if ($durationType === 'hours' && !$rule->allows_hourly) {
                throw ValidationException::withMessages([
                    'dates' => ['Hourly leave requests are not permitted for this leave type.']
                ]);
            }
        }

        // 6. Attachment Requirement Check
        if ($rule->requires_attachment && empty($files)) {
            throw ValidationException::withMessages([
                'attachments' => ['Supporting documentation/attachment is required for this leave request.']
            ]);
        }

        // 7. Balance Sufficiency & Negative Balance Check
        if (!$rule->allow_negative) {
            $totalRequestedDays = collect($dates)->sum(function ($d) {
                return match ($d['duration_type'] ?? 'full_day') {
                    'full_day' => 1.0,
                    'half_day' => 0.5,
                    'hours' => round(($d['hours'] ?? 0) / 8.0, 4),
                    default => 1.0,
                };
            });

            $balance = EmployeeLeaveBalance::where('employee_id', $employeeId)
                ->where('leave_type_id', $leaveTypeId)
                ->first();

            $availableBalance = ($balance->balance ?? 0) - ($balance->pending ?? 0);

            if ($availableBalance < $totalRequestedDays) {
                throw ValidationException::withMessages([
                    'leave_type_id' => ["Insufficient leave balance. Available: {$availableBalance} day(s), Requested: {$totalRequestedDays} day(s)."]
                ]);
            }
        }

        return $rule;
    }
}

<?php

namespace App\Services\Reports\Attendance;

use App\Services\Reports\Concerns\FiltersEmployees;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Raw punches joined to employees through their registered biometric IDs.
 * employee_attendance_logs.employee_id is the device's biometric ID string.
 */
trait RawAttendance
{
    use FiltersEmployees;

    public static function maxDays(): int
    {
        return 93;
    }

    protected function rangeFilters(): array
    {
        return [
            ['key' => 'date_from', 'label' => 'From', 'type' => 'date', 'required' => true, 'default' => now()->startOfMonth()->toDateString()],
            ['key' => 'date_to', 'label' => 'To', 'type' => 'date', 'required' => true, 'default' => now()->toDateString()],
        ];
    }

    protected function rangeRules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from', function ($attribute, $value, $fail) {
                $from = request()->input('date_from');
                if ($from && $value && Carbon::parse($from)->diffInDays(Carbon::parse($value)) + 1 > static::maxDays()) {
                    $fail('Choose a range of at most '.static::maxDays().' days.');
                }
            }],
        ];
    }

    protected function punches(array $filters): Builder
    {
        $query = DB::table('employee_attendance_logs as al')
            ->leftJoin('employee_biometric_ids as b', 'b.biometric_id', '=', 'al.employee_id')
            ->leftJoin('employees as e', 'e.id', '=', 'b.employee_id')
            ->leftJoin('departments as d', 'd.id', '=', 'e.department_id')
            ->whereBetween('al.auth_date', [$filters['date_from'], $filters['date_to']]);

        // Employee filters only make sense for registered IDs.
        $employeeFilters = array_intersect_key($filters, array_flip(['department_id', 'include_sub_departments']));

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($w) => $w
                ->where('al.employee_id', 'like', "%{$search}%")
                ->orWhere('e.emp_first_name', 'like', "%{$search}%")
                ->orWhere('e.emp_last_name', 'like', "%{$search}%")
                ->orWhere('e.employee_number', 'like', "%{$search}%"));
        }

        if (filter_var($filters['unregistered_only'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->whereNull('b.id');
        }

        return $this->applyEmployeeFilters($query, $employeeFilters);
    }
}

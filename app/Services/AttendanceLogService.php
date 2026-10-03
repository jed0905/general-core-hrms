<?php

namespace App\Services;

use App\Services\Reports\Concerns\FiltersEmployees;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Raw attendance punches (device/biometric logs), organization-wide.
 *
 * The single query and filter set behind both Time → Time Logs (operational
 * view) and Reports → Attendance Log / Summary (reporting and exports).
 * employee_attendance_logs.employee_id holds the device's biometric ID;
 * employees are reached through employee_biometric_ids. No DTR computation.
 */
class AttendanceLogService
{
    use FiltersEmployees;

    public const MAX_DAYS = 93;

    public function rangeFilters(): array
    {
        return [
            ['key' => 'date_from', 'label' => 'From', 'type' => 'date', 'required' => true, 'default' => now()->startOfMonth()->toDateString()],
            ['key' => 'date_to', 'label' => 'To', 'type' => 'date', 'required' => true, 'default' => now()->toDateString()],
        ];
    }

    /**
     * Filter schema for the UI. $only limits it to the given keys (in this order).
     */
    public function filters(?array $only = null): array
    {
        $all = collect([
            ...$this->rangeFilters(),
            ...$this->employeeFilters(['search', 'department_id', 'include_sub_departments', 'location_id']),
            // Not 'direction': reports use that key for sort order.
            ['key' => 'log_direction', 'label' => 'Direction', 'type' => 'select', 'options' => DB::table('employee_attendance_logs')->distinct()->orderBy('direction')->pluck('direction')
                ->map(fn ($d) => ['value' => $d, 'title' => ucwords($d)])->all()],
            ['key' => 'device_name', 'label' => 'Device', 'type' => 'select', 'options' => DB::table('employee_attendance_logs')->distinct()->orderBy('device_name')->pluck('device_name')
                ->map(fn ($d) => ['value' => $d, 'title' => $d])->all()],
            ['key' => 'unregistered_only', 'label' => 'Unregistered IDs only', 'type' => 'boolean', 'default' => false],
        ])->keyBy('key');

        return $only === null ? $all->values()->all() : collect($only)->map(fn ($key) => $all[$key])->all();
    }

    public function rules(): array
    {
        return array_merge($this->employeeFilterRules(), [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from', function ($attribute, $value, $fail) {
                $from = request()->input('date_from');
                if ($from && $value && Carbon::parse($from)->diffInDays(Carbon::parse($value)) + 1 > self::MAX_DAYS) {
                    $fail('Choose a range of at most '.self::MAX_DAYS.' days.');
                }
            }],
            'log_direction' => ['nullable', 'string', 'max:50'],
            'device_name' => ['nullable', 'string', 'max:191'],
            'unregistered_only' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * Punches in the date range with every filter applied, joined to the
     * registered employee, department and location. No select/order, so
     * callers can aggregate (the summary report) or list (query()).
     */
    public function filtered(array $filters): Builder
    {
        $query = DB::table('employee_attendance_logs as al')
            ->leftJoin('employee_biometric_ids as b', 'b.biometric_id', '=', 'al.employee_id')
            ->leftJoin('employees as e', 'e.id', '=', 'b.employee_id')
            ->leftJoin('departments as d', 'd.id', '=', 'e.department_id')
            ->leftJoin('locations as l', 'l.id', '=', 'e.location_id')
            ->whereBetween('al.auth_date', [$filters['date_from'], $filters['date_to']])
            ->when(! empty($filters['log_direction']), fn ($q) => $q->where('al.direction', $filters['log_direction']))
            ->when(! empty($filters['device_name']), fn ($q) => $q->where('al.device_name', $filters['device_name']))
            ->when(filter_var($filters['unregistered_only'] ?? false, FILTER_VALIDATE_BOOLEAN), fn ($q) => $q->whereNull('b.id'));

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($w) => $w
                ->where('al.employee_id', 'like', "%{$search}%")
                ->orWhere('e.emp_first_name', 'like', "%{$search}%")
                ->orWhere('e.emp_last_name', 'like', "%{$search}%")
                ->orWhere('e.employee_number', 'like', "%{$search}%"));
        }

        // Employee filters only match punches from registered IDs.
        return $this->applyEmployeeFilters($query, array_intersect_key($filters, array_flip(['department_id', 'include_sub_departments', 'location_id'])));
    }

    /**
     * One row per punch, newest first. Everything comes from one joined query (no N+1).
     */
    public function query(array $filters): Builder
    {
        return $this->filtered($filters)
            ->select([
                'al.id', 'al.auth_date', 'al.auth_time', 'al.direction', 'al.device_name', 'al.device_sn', 'al.card_no', 'al.person_name',
                'al.employee_id as biometric_id', 'b.id as registration_id',
                'e.id as employee_id', 'e.employee_number', 'e.emp_last_name', 'e.emp_first_name', 'e.department_id',
                'l.address as location_address', 'l.city as location_city',
            ])
            ->orderByDesc('al.auth_date_time')
            ->orderByDesc('al.id');
    }

    public function mapRow(object $row): array
    {
        return [
            'id' => $row->id,
            'date' => substr((string) $row->auth_date, 0, 10),
            'time' => $row->auth_time,
            'direction' => $row->direction,
            'device' => $row->device_name,
            'device_serial' => $row->device_sn,
            'card_no' => $row->card_no,
            'person_name' => $row->person_name,
            'biometric_id' => $row->biometric_id,
            'employee_id' => $row->registration_id ? $row->employee_id : null,
            'employee_number' => $row->employee_number,
            'employee' => $row->registration_id ? self::personName($row->emp_last_name, $row->emp_first_name) : 'Unregistered ID',
            'department' => $this->tree()->path($row->department_id),
            'location' => self::locationLabel($row->location_address, $row->location_city),
        ];
    }
}

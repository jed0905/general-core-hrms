<?php

namespace App\Services\Reports\Attendance;

use App\Models\AttendanceEvent;
use App\Services\Reports\Concerns\FiltersEmployees;
use App\Services\Reports\Report;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Raw attendance events as received by the attendance module
 * (attendance_events), from every source. Unmapped device users appear only
 * to viewers who may see all employees. Card numbers and payloads are never
 * included.
 */
class AttendanceLogReport extends Report
{
    use FiltersEmployees;
    use ScopesAttendance;

    public const MAX_DAYS = 93;

    public static function key(): string
    {
        return 'attendance-log';
    }

    public static function category(): string
    {
        return 'attendance';
    }

    public function title(): string
    {
        return 'Attendance Log';
    }

    public function description(): string
    {
        return 'Raw punches received from devices, connectors, manual entry and imports, with their source and mapping status.';
    }

    public function filters(): array
    {
        return [
            ['key' => 'date_from', 'label' => 'From', 'type' => 'date', 'required' => true, 'default' => now()->startOfMonth()->toDateString()],
            ['key' => 'date_to', 'label' => 'To', 'type' => 'date', 'required' => true, 'default' => now()->toDateString()],
            ...$this->employeeFilters(['search', 'department_id', 'include_sub_departments', 'location_id']),
            ['key' => 'device_id', 'label' => 'Device', 'type' => 'select', 'options' => DB::table('attendance_devices')->orderBy('name')->get(['id', 'name'])->map(fn ($d) => ['value' => $d->id, 'title' => $d->name])->all()],
            ['key' => 'source', 'label' => 'Source', 'type' => 'select', 'options' => array_map(fn ($s) => ['value' => $s, 'title' => ucfirst(str_replace('_', ' ', $s))], AttendanceEvent::SOURCES)],
            ['key' => 'event_status', 'label' => 'Mapping status', 'type' => 'select', 'options' => array_map(fn ($s) => ['value' => $s, 'title' => ucfirst($s)], [AttendanceEvent::STATUS_ACCEPTED, AttendanceEvent::STATUS_UNMATCHED, AttendanceEvent::STATUS_INVALID])],
        ];
    }

    public function rules(): array
    {
        return [...$this->employeeFilterRules(),
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from', function ($attribute, $value, $fail) {
                $from = request()->input('date_from');
                if ($from && $value && CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($value)) + 1 > self::MAX_DAYS) {
                    $fail('Choose a range of at most '.self::MAX_DAYS.' days.');
                }
            }],
            'device_id' => ['nullable', 'integer'],
            'source' => ['nullable', Rule::in(AttendanceEvent::SOURCES)],
            'event_status' => ['nullable', Rule::in([AttendanceEvent::STATUS_ACCEPTED, AttendanceEvent::STATUS_UNMATCHED, AttendanceEvent::STATUS_INVALID])],
        ];
    }

    public function notes(): array
    {
        return ['Raw punches as received; times are on each device\'s own clock. Calculated lateness, absences and hours are in the Daily Attendance report.'];
    }

    public function columns(array $filters = []): array
    {
        return [
            'date' => 'Date',
            'time' => 'Time',
            'type' => 'Type',
            'source' => 'Source',
            'device' => 'Device',
            'device_user_id' => 'Device User ID',
            'employee_number' => 'Employee No.',
            'employee' => 'Employee',
            'department' => 'Department',
            'status' => 'Status',
        ];
    }

    public function sortable(): array
    {
        return ['date' => 'ev.occurred_at', 'employee' => 'e.emp_last_name', 'device' => 'dv.name'];
    }

    protected function tiebreaker(): string|array
    {
        return 'ev.id';
    }

    public function query(array $filters): Builder
    {
        $query = DB::table('attendance_events as ev')
            ->leftJoin('employees as e', 'e.id', '=', 'ev.employee_id')
            ->leftJoin('attendance_devices as dv', 'dv.id', '=', 'ev.attendance_device_id')
            ->whereBetween('ev.local_date', [$filters['date_from'], $filters['date_to']])
            ->when($filters['device_id'] ?? null, fn ($q, $v) => $q->where('ev.attendance_device_id', $v))
            ->when($filters['source'] ?? null, fn ($q, $v) => $q->where('ev.source', $v))
            ->when($filters['event_status'] ?? null, fn ($q, $v) => $q->where('ev.status', $v));

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($w) => $w
                ->where('ev.device_user_id', 'like', "%{$search}%")
                ->orWhere('e.emp_first_name', 'like', "%{$search}%")
                ->orWhere('e.emp_last_name', 'like', "%{$search}%")
                ->orWhere('e.employee_number', 'like', "%{$search}%"));
        }

        $query = $this->applyEmployeeFilters($query, array_intersect_key($filters, array_flip(['department_id', 'include_sub_departments', 'location_id'])));

        return $this->scopeEmployees($query, 'ev.employee_id')
            ->select([
                'ev.id', 'ev.occurred_at', 'ev.device_timezone', 'ev.event_type', 'ev.source', 'ev.device_user_id', 'ev.status',
                'dv.name as device_name', 'e.employee_number', 'e.emp_last_name', 'e.emp_first_name', 'e.department_id',
            ])
            ->orderByDesc('ev.occurred_at')
            ->orderByDesc('ev.id');
    }

    public function mapRow(mixed $row, array $filters): array
    {
        $local = CarbonImmutable::parse($row->occurred_at, 'UTC')->setTimezone($row->device_timezone ?: config('app.timezone'));

        return [
            'date' => $local->toDateString(),
            'time' => $local->format('H:i:s'),
            'type' => str_replace('_', ' ', (string) $row->event_type),
            'source' => str_replace('_', ' ', (string) $row->source),
            'device' => $row->device_name,
            'device_user_id' => $row->device_user_id,
            'employee_number' => $row->employee_number,
            'employee' => $row->employee_number ? self::personName($row->emp_last_name, $row->emp_first_name) : 'Unmapped device user',
            'department' => $this->tree()->path($row->department_id),
            'status' => $row->status,
        ];
    }
}

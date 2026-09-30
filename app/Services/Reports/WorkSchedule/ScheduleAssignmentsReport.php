<?php

namespace App\Services\Reports\WorkSchedule;

use App\Models\Shift;
use App\Models\WorkSchedule;
use App\Services\Reports\Concerns\FiltersEmployees;
use App\Services\Reports\Report;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Effective-dated schedule assignments (employee_work_schedules).
 */
class ScheduleAssignmentsReport extends Report
{
    use FiltersEmployees;

    private const DAY_NAMES = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    private ?array $patterns = null;

    public static function key(): string
    {
        return 'schedule-assignments';
    }

    public static function category(): string
    {
        return 'work_schedule';
    }

    public function title(): string
    {
        return 'Work Schedule Assignments';
    }

    public function description(): string
    {
        return 'Which work schedule each employee is assigned to, and when.';
    }

    public function filters(): array
    {
        return [
            ...$this->employeeFilters(['search', 'department_id', 'include_sub_departments']),
            ['key' => 'work_schedule_id', 'label' => 'Schedule', 'type' => 'select', 'options' => WorkSchedule::orderBy('name')->get(['id', 'name', 'code'])
                ->map(fn ($s) => ['value' => $s->id, 'title' => $s->code ? "{$s->name} ({$s->code})" : $s->name])->all()],
            ['key' => 'shift_id', 'label' => 'Uses shift', 'type' => 'select', 'options' => Shift::orderBy('name')->get(['id', 'name', 'code'])
                ->map(fn ($s) => ['value' => $s->id, 'title' => "{$s->name} ({$s->code})"])->all()],
            ['key' => 'state', 'label' => 'Assignment', 'type' => 'select', 'default' => 'current', 'options' => [
                ['value' => 'current', 'title' => 'In effect on date'],
                ['value' => 'upcoming', 'title' => 'Starts after date'],
                ['value' => 'ended', 'title' => 'Ended before date'],
                ['value' => 'all', 'title' => 'All'],
            ]],
            ['key' => 'as_of', 'label' => 'Date', 'type' => 'date', 'default' => now()->toDateString()],
        ];
    }

    public function rules(): array
    {
        return array_merge($this->employeeFilterRules(), [
            'work_schedule_id' => ['nullable', 'integer', 'exists:work_schedules,id'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'state' => ['nullable', Rule::in(['current', 'upcoming', 'ended', 'all'])],
            'as_of' => ['nullable', 'date_format:Y-m-d'],
        ]);
    }

    public function columns(array $filters = []): array
    {
        return [
            'employee_number' => 'Employee No.',
            'name' => 'Name',
            'department' => 'Department',
            'schedule' => 'Schedule',
            'weekly_pattern' => 'Weekly Pattern',
            'effective_from' => 'Effective From',
            'effective_to' => 'Effective To',
            'primary' => 'Primary',
            'state' => 'State',
        ];
    }

    public function sortable(): array
    {
        return ['name' => 'e.emp_last_name', 'schedule' => 'ws.name', 'effective_from' => 'ews.effective_from', 'department' => 'd.name'];
    }

    protected function tiebreaker(): string|array
    {
        return 'ews.id';
    }

    public function query(array $filters): Builder
    {
        $asOf = $filters['as_of'] ?? now()->toDateString();

        return $this->applyEmployeeFilters(
            DB::table('employee_work_schedules as ews')
                ->join('employees as e', 'e.id', '=', 'ews.employee_id')
                ->join('work_schedules as ws', 'ws.id', '=', 'ews.work_schedule_id')
                ->leftJoin('departments as d', 'd.id', '=', 'e.department_id'),
            $filters
        )
            ->when(! empty($filters['work_schedule_id']), fn ($q) => $q->where('ews.work_schedule_id', $filters['work_schedule_id']))
            ->when(! empty($filters['shift_id']), fn ($q) => $q->whereExists(fn ($sub) => $sub
                ->from('work_schedule_days as wsd')
                ->whereColumn('wsd.work_schedule_id', 'ews.work_schedule_id')
                ->where('wsd.shift_id', $filters['shift_id'])
                ->where('wsd.is_working_day', true)))
            ->when(($filters['state'] ?? 'current') === 'current', fn ($q) => $q
                ->where('ews.effective_from', '<=', $asOf)
                ->where(fn ($w) => $w->whereNull('ews.effective_to')->orWhere('ews.effective_to', '>=', $asOf)))
            ->when(($filters['state'] ?? null) === 'upcoming', fn ($q) => $q->where('ews.effective_from', '>', $asOf))
            ->when(($filters['state'] ?? null) === 'ended', fn ($q) => $q->whereNotNull('ews.effective_to')->where('ews.effective_to', '<', $asOf))
            ->select(['ews.id', 'ews.work_schedule_id', 'ews.effective_from', 'ews.effective_to', 'ews.is_primary', 'e.employee_number', 'e.emp_last_name', 'e.emp_first_name', 'e.department_id', 'ws.name as schedule_name', 'ws.code as schedule_code'])
            ->orderBy('e.emp_last_name')
            ->orderByDesc('ews.effective_from')
            ->orderBy('ews.id');
    }

    public function mapRow(mixed $row, array $filters): array
    {
        $asOf = $filters['as_of'] ?? now()->toDateString();
        $from = substr((string) $row->effective_from, 0, 10);
        $to = $row->effective_to ? substr((string) $row->effective_to, 0, 10) : null;

        return [
            'employee_number' => $row->employee_number,
            'name' => self::personName($row->emp_last_name, $row->emp_first_name),
            'department' => $this->tree()->path($row->department_id),
            'schedule' => $row->schedule_code ? "{$row->schedule_name} ({$row->schedule_code})" : $row->schedule_name,
            'weekly_pattern' => $this->patterns()[$row->work_schedule_id] ?? null,
            'effective_from' => $from,
            'effective_to' => $to,
            'primary' => $row->is_primary ? 'Yes' : 'No',
            'state' => match (true) {
                $from > $asOf => 'Upcoming',
                $to !== null && $to < $asOf => 'Ended',
                default => 'In effect',
            },
        ];
    }

    /**
     * "Mon–Fri 08:00–17:00 (Day)" per schedule, loaded once.
     */
    private function patterns(): array
    {
        if ($this->patterns !== null) {
            return $this->patterns;
        }

        $days = DB::table('work_schedule_days as wsd')
            ->leftJoin('shifts as s', 's.id', '=', 'wsd.shift_id')
            ->where('wsd.is_working_day', true)
            ->orderBy('wsd.day_of_week')
            ->get(['wsd.work_schedule_id', 'wsd.day_of_week', 's.name', 's.start_time', 's.end_time']);

        return $this->patterns = $days->groupBy('work_schedule_id')->map(fn ($scheduleDays) => $scheduleDays
            ->groupBy(fn ($d) => $d->name.substr((string) $d->start_time, 0, 5).substr((string) $d->end_time, 0, 5))
            ->map(fn ($group) => implode(',', $group->map(fn ($d) => self::DAY_NAMES[$d->day_of_week % 7])->all())
                .' '.substr((string) $group->first()->start_time, 0, 5).'–'.substr((string) $group->first()->end_time, 0, 5)
                .($group->first()->name ? " ({$group->first()->name})" : ''))
            ->implode('; '))
            ->all();
    }
}

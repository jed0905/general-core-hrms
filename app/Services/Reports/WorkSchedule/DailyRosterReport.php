<?php

namespace App\Services\Reports\WorkSchedule;

use App\Models\Shift;
use App\Services\EmployeeWorkScheduleService;
use App\Services\HolidayService;
use App\Services\Reports\Concerns\FiltersEmployees;
use App\Services\Reports\Report;
use Carbon\CarbonPeriod;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Derived, not stored: for every employee and date, the assignment in effect,
 * that weekday's shift and any holiday, using the same rule as the dashboard
 * (EmployeeWorkScheduleService::resolveDay). Paginated by employee.
 */
class DailyRosterReport extends Report
{
    use FiltersEmployees;

    public const MAX_DAYS = 31;

    public static function key(): string
    {
        return 'daily-roster';
    }

    public static function category(): string
    {
        return 'work_schedule';
    }

    public function title(): string
    {
        return 'Daily Work Roster';
    }

    public function description(): string
    {
        return 'Each employee\'s scheduled shift per day, including rest days and holidays (up to '.self::MAX_DAYS.' days).';
    }

    public function filters(): array
    {
        return [
            ['key' => 'date_from', 'label' => 'From', 'type' => 'date', 'required' => true, 'default' => now()->startOfWeek()->toDateString()],
            ['key' => 'date_to', 'label' => 'To', 'type' => 'date', 'required' => true, 'default' => now()->endOfWeek()->toDateString()],
            ...$this->employeeFilters(['search', 'department_id', 'include_sub_departments', 'status']),
            ['key' => 'shift_id', 'label' => 'Shift', 'type' => 'select', 'options' => Shift::orderBy('name')->get(['id', 'name', 'code'])
                ->map(fn ($s) => ['value' => $s->id, 'title' => "{$s->name} ({$s->code})"])->all()],
        ];
    }

    public function rules(): array
    {
        return array_merge($this->employeeFilterRules(), [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from', function ($attribute, $value, $fail) {
                $from = request()->input('date_from');
                if ($from && $value && Carbon::parse($from)->diffInDays(Carbon::parse($value)) + 1 > self::MAX_DAYS) {
                    $fail('The roster covers at most '.self::MAX_DAYS.' days.');
                }
            }],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
        ]);
    }

    public function columns(array $filters = []): array
    {
        return [
            'date' => 'Date',
            'day' => 'Day',
            'employee_number' => 'Employee No.',
            'name' => 'Name',
            'department' => 'Department',
            'schedule' => 'Schedule',
            'day_type' => 'Day Type',
            'shift' => 'Shift',
            'start_time' => 'Start',
            'end_time' => 'End',
        ];
    }

    public function notes(): array
    {
        return ['Derived from schedule assignments, weekly patterns and holidays. It is a plan, not attendance.'];
    }

    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $employees = $this->employees($filters)->paginate(min($perPage, 25))->withQueryString();

        return new LengthAwarePaginator(
            $this->rosterFor(collect($employees->items()), $filters),
            $employees->total(),
            $employees->perPage(),
            $employees->currentPage(),
            ['path' => $employees->path(), 'query' => request()->query(), 'pageName' => 'page']
        );
    }

    public function rows(array $filters): iterable
    {
        foreach ($this->employees($filters)->lazy(100)->chunk(100) as $chunk) {
            yield from $this->rosterFor(collect($chunk->all()), $filters);
        }
    }

    private function employees(array $filters)
    {
        return $this->applyEmployeeFilters(
            DB::table('employees as e')->leftJoin('departments as d', 'd.id', '=', 'e.department_id'),
            $filters
        )
            ->select(['e.id', 'e.employee_number', 'e.emp_last_name', 'e.emp_first_name', 'e.department_id'])
            ->orderBy('e.emp_last_name')
            ->orderBy('e.emp_first_name')
            ->orderBy('e.id');
    }

    /**
     * Two queries per batch (assignments, holidays), then pure resolution per day.
     */
    private function rosterFor(Collection $employees, array $filters): array
    {
        if ($employees->isEmpty()) {
            return [];
        }

        $from = Carbon::parse($filters['date_from']);
        $to = Carbon::parse($filters['date_to']);
        $schedules = app(EmployeeWorkScheduleService::class);
        $holidayService = app(HolidayService::class);

        $assignments = $schedules->assignmentsQuery($employees->pluck('id'), $from->toDateString(), $to->toDateString())
            ->get()
            ->groupBy('employee_id');
        $holidays = $holidayService->getForRange($from, $to);

        $rows = [];

        foreach ($employees as $employee) {
            foreach (CarbonPeriod::create($from, $to) as $date) {
                $day = $schedules->resolveDay($assignments->get($employee->id, collect()), $date, $holidayService->matchDate($holidays, $date));

                if (! empty($filters['shift_id']) && (int) ($day['shift']['id'] ?? 0) !== (int) $filters['shift_id']) {
                    continue;
                }

                $rows[] = [
                    'date' => $day['date'],
                    'day' => $date->format('D'),
                    'employee_number' => $employee->employee_number,
                    'name' => self::personName($employee->emp_last_name, $employee->emp_first_name),
                    'department' => $this->tree()->path($employee->department_id),
                    'schedule' => $day['schedule']['name'] ?? null,
                    'day_type' => match (true) {
                        ! $day['has_schedule'] => 'No schedule',
                        $day['holiday'] && ! $day['holiday']['is_working_day'] => 'Holiday: '.$day['holiday']['name'],
                        $day['is_working_day'] => 'Working day',
                        default => 'Rest day',
                    },
                    'shift' => $day['is_working_day'] ? ($day['shift']['name'] ?? null) : null,
                    'start_time' => $day['is_working_day'] ? substr((string) ($day['shift']['start_time'] ?? ''), 0, 5) : null,
                    'end_time' => $day['is_working_day'] ? substr((string) ($day['shift']['end_time'] ?? ''), 0, 5) : null,
                ];
            }
        }

        return $rows;
    }
}

<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeWorkSchedule;
use App\Models\Holiday;
use App\Models\WorkSchedule;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EmployeeWorkScheduleService
{
    public function getPaginatedSchedules(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return EmployeeWorkSchedule::query()
            ->with([
                'employee:id,emp_first_name,emp_last_name,employee_number,department_id',
                'employee.department:id,name',
                'workSchedule:id,name,code',
            ])
            ->when(! empty($filters['search']), function ($query) use ($filters) {
                $search = $filters['search'];
                $query->whereHas('employee', function ($q) use ($search) {
                    $q->where('emp_first_name', 'like', "%{$search}%")
                        ->orWhere('emp_last_name', 'like', "%{$search}%")
                        ->orWhere('employee_number', 'like', "%{$search}%");
                });
            })
            ->when(! empty($filters['work_schedule_id']), function ($query) use ($filters) {
                $query->where('work_schedule_id', $filters['work_schedule_id']);
            })
            ->when(! empty($filters['department_id']), function ($query) use ($filters) {
                $query->whereHas('employee', function ($q) use ($filters) {
                    $q->where('department_id', $filters['department_id']);
                });
            })
            ->when(isset($filters['is_primary']) && $filters['is_primary'] !== null, function ($query) use ($filters) {
                $query->where('is_primary', filter_var($filters['is_primary'], FILTER_VALIDATE_BOOLEAN));
            })
            ->latest('effective_from')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Every schedule assignment of one employee, newest first, with the
     * weekly days and shifts. Callers pass an already-authorized employee.
     */
    public function getSchedulesForEmployee(Employee $employee): Collection
    {
        $today = now()->toDateString();

        return EmployeeWorkSchedule::query()
            ->where('employee_id', $employee->id)
            ->with([
                'workSchedule:id,name,code,description',
                'workSchedule.days' => fn ($q) => $q->orderBy('day_of_week'),
                'workSchedule.days.shift:id,name,code,start_time,end_time,break_start,break_end,required_hours,is_overnight,is_flexible',
            ])
            ->orderByDesc('effective_from')
            ->get()
            ->map(function (EmployeeWorkSchedule $assignment) use ($today) {
                $assignment->setAttribute('is_current',
                    $assignment->effective_from <= $today
                    && ($assignment->effective_to === null || $assignment->effective_to >= $today)
                );

                return $assignment;
            });
    }

    /**
     * What an employee is scheduled to do on one date: the assignment in
     * effect (primary first), that weekday's shift, and any holiday.
     */
    public function getDaySchedule(Employee $employee, CarbonInterface $date, ?HolidayService $holidays = null): array
    {
        $holidays ??= app(HolidayService::class);
        $day = $date->toDateString();

        $assignments = $this->assignmentsQuery([$employee->id], $day, $day)->get();

        return $this->resolveDay($assignments, $date, $holidays->findForDate($date));
    }

    /**
     * Assignments overlapping [$from, $to] for the given employees, with the
     * weekly days and shifts needed by resolveDay().
     */
    public function assignmentsQuery(iterable $employeeIds, string $from, string $to): Builder
    {
        return EmployeeWorkSchedule::query()
            ->whereIn('employee_id', collect($employeeIds)->all())
            ->where('effective_from', '<=', $to)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from))
            ->with(['workSchedule:id,name,code', 'workSchedule.days.shift:id,name,code,start_time,end_time,required_hours,is_overnight']);
    }

    /**
     * The single rule for "what is this employee scheduled to do on $date",
     * given their assignments and the holiday on that date (if any).
     * Used by the dashboard and by the daily roster report.
     */
    public function resolveDay(Collection $assignments, CarbonInterface $date, ?Holiday $holiday): array
    {
        $day = $date->toDateString();

        // In effect on $day; primary first, then the most recent.
        $assignment = $assignments
            ->filter(fn (EmployeeWorkSchedule $a) => substr((string) $a->effective_from, 0, 10) <= $day
                && ($a->effective_to === null || substr((string) $a->effective_to, 0, 10) >= $day))
            ->sortBy([['is_primary', 'desc'], ['effective_from', 'desc']])
            ->first();

        $scheduleDay = $assignment?->workSchedule?->days->firstWhere('day_of_week', $date->dayOfWeek);
        $isWorkingDay = (bool) ($scheduleDay?->is_working_day && $scheduleDay?->shift)
            && ! ($holiday && ! $holiday->is_working_day);

        return [
            'date' => $day,
            'has_schedule' => $assignment !== null,
            'schedule' => $assignment?->workSchedule?->only(['id', 'name', 'code']),
            'is_working_day' => $isWorkingDay,
            'shift' => $scheduleDay?->is_working_day ? $scheduleDay->shift?->only(['id', 'name', 'code', 'start_time', 'end_time', 'required_hours', 'is_overnight']) : null,
            'holiday' => $holiday?->only(['name', 'type', 'is_working_day']),
        ];
    }

    /**
     * The first working day strictly after $from within $horizonDays, or null.
     */
    public function getNextWorkingDay(Employee $employee, CarbonInterface $from, int $horizonDays = 31): ?array
    {
        $holidays = app(HolidayService::class);

        for ($i = 1; $i <= $horizonDays; $i++) {
            $day = $this->getDaySchedule($employee, $from->copy()->addDays($i), $holidays);

            if ($day['is_working_day']) {
                return $day;
            }
        }

        return null;
    }

    public function assignSchedules(array $data): void
    {
        DB::transaction(function () use ($data) {
            $effectiveFrom = Carbon::parse($data['effective_from']);
            $previousEndDate = $effectiveFrom->copy()->subDay()->format('Y-m-d');
            $isPrimary = $data['is_primary'] ?? true;

            foreach ($data['employee_ids'] as $employeeId) {
                // Auto-close previous open-ended primary schedules prior to the new effective date
                if ($isPrimary) {
                    EmployeeWorkSchedule::where('employee_id', $employeeId)
                        ->where('is_primary', true)
                        ->whereNull('effective_to')
                        ->where('effective_from', '<', $data['effective_from'])
                        ->update([
                            'effective_to' => $previousEndDate,
                        ]);
                }

                EmployeeWorkSchedule::create([
                    'employee_id' => $employeeId,
                    'work_schedule_id' => $data['work_schedule_id'],
                    'effective_from' => $data['effective_from'],
                    'effective_to' => $data['effective_to'] ?? null,
                    'is_primary' => $isPrimary,
                    'remarks' => $data['remarks'] ?? null,
                ]);
            }
        });
    }

    public function updateSchedule(EmployeeWorkSchedule $schedule, array $data): EmployeeWorkSchedule
    {
        return DB::transaction(function () use ($schedule, $data) {
            $schedule->update([
                'work_schedule_id' => $data['work_schedule_id'],
                'effective_from' => $data['effective_from'],
                'effective_to' => $data['effective_to'] ?? null,
                'is_primary' => $data['is_primary'] ?? $schedule->is_primary,
                'remarks' => $data['remarks'] ?? null,
            ]);

            return $schedule->fresh();
        });
    }

    public function removeSchedule(EmployeeWorkSchedule $schedule): bool
    {
        return DB::transaction(function () use ($schedule) {
            return $schedule->delete();
        });
    }

    public function getFormData(): array
    {
        return [
            'workSchedules' => WorkSchedule::where('status', 'active')
                ->select('id', 'name', 'code')
                ->get(),
            'employees' => Employee::select('id', 'emp_first_name', 'emp_last_name', 'employee_number', 'department_id')
                ->orderBy('emp_first_name')
                ->get(),
            'departments' => Department::select('id', 'name')->orderBy('name')->get(),
        ];
    }
}

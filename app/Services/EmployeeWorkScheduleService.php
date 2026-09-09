<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeWorkSchedule;
use App\Models\WorkSchedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
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
            ->when(!empty($filters['search']), function ($query) use ($filters) {
                $search = $filters['search'];
                $query->whereHas('employee', function ($q) use ($search) {
                    $q->where('emp_first_name', 'like', "%{$search}%")
                        ->orWhere('emp_last_name', 'like', "%{$search}%")
                        ->orWhere('employee_number', 'like', "%{$search}%");
                });
            })
            ->when(!empty($filters['work_schedule_id']), function ($query) use ($filters) {
                $query->where('work_schedule_id', $filters['work_schedule_id']);
            })
            ->when(!empty($filters['department_id']), function ($query) use ($filters) {
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

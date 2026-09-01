<?php

namespace App\Services;

use App\Models\WorkSchedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class WorkScheduleService
{
    public function getPaginatedSchedules(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return WorkSchedule::query()
            ->with(['days.shift'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, function ($query, $status) {
                $query->where('status', $status);
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function createSchedule(array $data): WorkSchedule
    {
        return DB::transaction(function () use ($data) {
            $daysData = Arr::pull($data, 'days', []);

            $schedule = WorkSchedule::create($data);

            if (!empty($daysData)) {
                $schedule->days()->createMany($daysData);
            }

            return $schedule->load('days.shift');
        });
    }

    public function updateSchedule(WorkSchedule $schedule, array $data): WorkSchedule
    {
        return DB::transaction(function () use ($schedule, $data) {
            $daysData = Arr::pull($data, 'days', []);

            $schedule->update($data);

            if (!empty($daysData)) {
                foreach ($daysData as $day) {
                    $schedule->days()->updateOrCreate(
                        ['day_of_week' => $day['day_of_week']],
                        [
                            'shift_id' => $day['is_working_day'] ? $day['shift_id'] : null,
                            'is_working_day' => $day['is_working_day'],
                        ]
                    );
                }
            }

            return $schedule->fresh('days.shift');
        });
    }

    public function archiveSchedule(WorkSchedule $schedule): bool
    {
        return $schedule->update(['status' => 'inactive']);
    }
}

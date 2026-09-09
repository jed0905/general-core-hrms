<?php

namespace App\Services;

use App\Models\Holiday;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class HolidayService
{
    public function getPaginatedHolidays(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Holiday::query()
            ->when(!empty($filters['search']), function ($query) use ($filters) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when(!empty($filters['year']), function ($query) use ($filters) {
                $query->whereYear('date', $filters['year']);
            })
            ->when(!empty($filters['type']), function ($query) use ($filters) {
                $query->where('type', $filters['type']);
            })
            ->when(!empty($filters['status']), function ($query) use ($filters) {
                $query->where('status', $filters['status']);
            })
            ->orderBy('date', 'asc')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function createHoliday(array $data): Holiday
    {
        return DB::transaction(function () use ($data) {
            return Holiday::create([
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'date' => $data['date'],
                'type' => $data['type'],
                'is_paid' => $data['is_paid'] ?? true,
                'is_working_day' => $data['is_working_day'] ?? false,
                'is_recurring' => $data['is_recurring'] ?? false,
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'active',
            ]);
        });
    }

    public function updateHoliday(Holiday $holiday, array $data): Holiday
    {
        return DB::transaction(function () use ($holiday, $data) {
            $holiday->update([
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'date' => $data['date'],
                'type' => $data['type'],
                'is_paid' => $data['is_paid'] ?? false,
                'is_working_day' => $data['is_working_day'] ?? false,
                'is_recurring' => $data['is_recurring'] ?? false,
                'description' => $data['description'] ?? null,
                'status' => $data['status'],
            ]);

            return $holiday->fresh();
        });
    }

    public function deleteHoliday(Holiday $holiday): bool
    {
        return DB::transaction(function () use ($holiday) {
            return $holiday->delete();
        });
    }
}

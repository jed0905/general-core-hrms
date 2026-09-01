<?php

namespace App\Services;

use App\Models\Shift;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ShiftService
{
    public function getPaginatedShifts(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Shift::query()
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when(isset($filters['is_active']), function ($query) use ($filters) {
                $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function createShift(array $data): Shift
    {
        return Shift::create($data);
    }

    public function updateShift(Shift $shift, array $data): Shift
    {
        $shift->update($data);

        return $shift->fresh();
    }

    public function deleteShift(Shift $shift): bool
    {
        return $shift->delete();
    }
}

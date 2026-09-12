<?php

namespace App\Services;

use App\Models\LeaveType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LeaveTypeService
{
    public function getPaginatedTypes(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return LeaveType::query()
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $search = $filters['search'];
                $q->where(
                    fn($query) =>
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                );
            })
            ->when(isset($filters['is_active']) && $filters['is_active'] !== null, function ($q) use ($filters) {
                $q->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function createType(array $data): LeaveType
    {
        return DB::transaction(fn() => LeaveType::create([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]));
    }

    public function updateType(LeaveType $leaveType, array $data): LeaveType
    {
        return DB::transaction(function () use ($leaveType, $data) {
            $leaveType->update([
                'name' => $data['name'],
                'code' => strtoupper($data['code']),
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? false,
            ]);

            return $leaveType->fresh();
        });
    }

    public function archiveType(LeaveType $leaveType): bool
    {
        return DB::transaction(fn() => $leaveType->update(['is_active' => false]));
    }
}

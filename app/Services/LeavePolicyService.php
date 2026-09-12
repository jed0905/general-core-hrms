<?php

namespace App\Services;

use App\Models\LeavePolicy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LeavePolicyService
{
    public function getPaginatedPolicies(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return LeavePolicy::query()
            ->withCount('rules')
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%");
            })
            ->when(isset($filters['is_active']) && $filters['is_active'] !== null, function ($q) use ($filters) {
                $q->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function createPolicy(array $data): LeavePolicy
    {
        return DB::transaction(fn() => LeavePolicy::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]));
    }

    public function updatePolicy(LeavePolicy $leavePolicy, array $data): LeavePolicy
    {
        return DB::transaction(function () use ($leavePolicy, $data) {
            $leavePolicy->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'effective_from' => $data['effective_from'],
                'effective_to' => $data['effective_to'] ?? null,
                'is_active' => $data['is_active'] ?? false,
            ]);

            return $leavePolicy->fresh();
        });
    }

    public function archivePolicy(LeavePolicy $leavePolicy): bool
    {
        return DB::transaction(fn() => $leavePolicy->update(['is_active' => false]));
    }
}

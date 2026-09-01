<?php

namespace App\Services;

use App\Models\EmploymentStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EmploymentStatusService
{
    /**
     * Get paginated list of employment statuses with optional search filtering.
     */
    public function getPaginated(int $perPage = 10, ?string $search = null): LengthAwarePaginator
    {
        return EmploymentStatus::query()
            ->when($search, fn($query, $s) => $query->where('name', 'like', "%{$s}%"))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Create a new employment status.
     */
    public function create(array $data): EmploymentStatus
    {
        return EmploymentStatus::create($data);
    }

    /**
     * Update an existing employment status.
     */
    public function update(EmploymentStatus $employmentStatus, array $data): bool
    {
        return $employmentStatus->update($data);
    }

    /**
     * Archive (soft-delete) an employment status.
     */
    public function archive(EmploymentStatus $employmentStatus): bool
    {
        return $employmentStatus->delete();
    }
}

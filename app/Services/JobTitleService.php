<?php

namespace App\Services;

use App\Models\JobTitle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class JobTitleService
{
    /**
     * Get paginated job titles with search filter and employee counts.
     */
    public function getPaginatedJobTitles(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return JobTitle::query()
            ->withCount('employees')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where('job_title', 'like', "%{$search}%")
                    ->orWhere('job_description', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Store a new job title record.
     */
    public function createJobTitle(array $data): JobTitle
    {
        return JobTitle::create($data);
    }

    /**
     * Update an existing job title record.
     */
    public function updateJobTitle(JobTitle $jobTitle, array $data): JobTitle
    {
        $jobTitle->update($data);

        return $jobTitle;
    }

    /**
     * Remove or archive a job title record.
     */
    public function archiveJobTitle(JobTitle $jobTitle): bool
    {
        return $jobTitle->delete();
    }
}

<?php

namespace App\Services\Recruitment;

use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\JobOffer;
use App\Models\JobTitle;
use App\Models\Location;
use App\Services\Reports\DepartmentTree;
use Illuminate\Support\Collection;

/**
 * Dropdown data for recruitment forms, from the Core HR master tables.
 */
class RecruitmentFormOptions
{
    public function __construct(protected DepartmentTree $departments) {}

    public function all(): array
    {
        return [
            'departments' => $this->departments->options(),
            'job_titles' => JobTitle::orderBy('job_title')->get(['id', 'job_title'])
                ->map(fn ($t) => ['value' => $t->id, 'title' => $t->job_title]),
            'locations' => Location::orderBy('address')->get(['id', 'address', 'city'])
                ->map(fn ($l) => ['value' => $l->id, 'title' => trim(implode(', ', array_filter([$l->address, $l->city]))) ?: "Location #{$l->id}"]),
            'employment_statuses' => EmploymentStatus::orderBy('name')->get(['id', 'name'])
                ->map(fn ($s) => ['value' => $s->id, 'title' => $s->name]),
            'employees' => $this->employees(),
        ];
    }

    /** Dropdowns for offer terms. */
    public function offerTerms(): array
    {
        return [
            'job_titles' => JobTitle::orderBy('job_title')->get(['id', 'job_title'])
                ->map(fn ($t) => ['value' => $t->id, 'title' => $t->job_title]),
            'locations' => Location::orderBy('address')->get(['id', 'address', 'city'])
                ->map(fn ($l) => ['value' => $l->id, 'title' => trim(implode(', ', array_filter([$l->address, $l->city]))) ?: "Location #{$l->id}"]),
            'employment_statuses' => EmploymentStatus::orderBy('name')->get(['id', 'name'])
                ->map(fn ($s) => ['value' => $s->id, 'title' => $s->name]),
            'frequencies' => JobOffer::FREQUENCIES,
        ];
    }

    /** Current employees (hiring managers, panelists, assessors). */
    public function employees(): Collection
    {
        return Employee::whereNotIn('status', ['archived', 'terminated'])
            ->orderBy('emp_last_name')->orderBy('emp_first_name')
            ->get(['id', 'employee_number', 'emp_first_name', 'emp_last_name'])
            ->map(fn ($e) => ['value' => $e->id, 'title' => "{$e->emp_last_name}, {$e->emp_first_name} ({$e->employee_number})"]);
    }
}

<?php

namespace App\Services\Reports\Concerns;

use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\JobTitle;
use App\Models\Location;
use App\Services\Reports\DepartmentTree;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Employee filters shared by the employee-based reports, so every report
 * filters the same way. Expects the employees table aliased as "e".
 */
trait FiltersEmployees
{
    protected ?DepartmentTree $tree = null;

    protected function tree(): DepartmentTree
    {
        return $this->tree ??= app(DepartmentTree::class);
    }

    /**
     * @param  array<int, string>  $keys  which employee filters this report offers
     */
    protected function employeeFilters(array $keys, array $defaults = []): array
    {
        $all = [
            'search' => fn () => ['key' => 'search', 'label' => 'Employee', 'type' => 'text'],
            'department_id' => fn () => ['key' => 'department_id', 'label' => 'Department', 'type' => 'select', 'options' => $this->tree()->options()],
            'include_sub_departments' => fn () => ['key' => 'include_sub_departments', 'label' => 'Include sub-departments', 'type' => 'boolean', 'default' => true],
            'job_title_id' => fn () => ['key' => 'job_title_id', 'label' => 'Job Title', 'type' => 'select', 'options' => [
                ['value' => 'none', 'title' => 'Not specified'],
                ...JobTitle::orderBy('job_title')->get(['id', 'job_title'])->map(fn ($j) => ['value' => $j->id, 'title' => $j->job_title])->all(),
            ]],
            'employment_status_id' => fn () => ['key' => 'employment_status_id', 'label' => 'Employment Status', 'type' => 'select', 'options' => [
                ['value' => 'none', 'title' => 'Not specified'],
                ...EmploymentStatus::orderBy('name')->get(['id', 'name'])->map(fn ($s) => ['value' => $s->id, 'title' => $s->name])->all(),
            ]],
            'location_id' => fn () => ['key' => 'location_id', 'label' => 'Location', 'type' => 'select', 'options' => Location::orderBy('city')->get(['id', 'city', 'address'])
                ->map(fn ($l) => ['value' => $l->id, 'title' => trim(implode(', ', array_filter([$l->address, $l->city]))) ?: "Location #{$l->id}"])->all()],
            'sex' => fn () => ['key' => 'sex', 'label' => 'Sex', 'type' => 'select', 'options' => DB::table('employees')->whereNotNull('emp_sex')->distinct()->orderBy('emp_sex')->pluck('emp_sex')
                ->map(fn ($s) => ['value' => $s, 'title' => ucfirst($s)])->all()],
            'supervisor_id' => fn () => ['key' => 'supervisor_id', 'label' => 'Supervisor', 'type' => 'select', 'options' => Employee::whereIn('id', Employee::whereNotNull('supervisor_id')->select('supervisor_id'))
                ->orderBy('emp_last_name')->get(['id', 'emp_first_name', 'emp_last_name'])
                ->map(fn ($e) => ['value' => $e->id, 'title' => "{$e->emp_last_name}, {$e->emp_first_name}"])->all()],
            'status' => fn () => ['key' => 'status', 'label' => 'Record Status', 'type' => 'select', 'default' => 'active', 'options' => [
                ['value' => 'all', 'title' => 'All'],
                ...collect(Employee::STATUSES)->map(fn ($s) => ['value' => $s, 'title' => ucwords(str_replace('_', ' ', $s))])->all(),
            ]],
            'joined_from' => fn () => ['key' => 'joined_from', 'label' => 'Joined from', 'type' => 'date'],
            'joined_to' => fn () => ['key' => 'joined_to', 'label' => 'Joined to', 'type' => 'date'],
        ];

        return collect($keys)->map(function ($key) use ($all, $defaults) {
            $filter = $all[$key]();

            if (array_key_exists($key, $defaults)) {
                $filter['default'] = $defaults[$key];
            }

            return $filter;
        })->all();
    }

    protected function employeeFilterRules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'include_sub_departments' => ['nullable', 'boolean'],
            'job_title_id' => ['nullable', fn ($attr, $value, $fail) => $value === 'none' || JobTitle::whereKey($value)->exists() || $fail('The selected job title is invalid.')],
            'employment_status_id' => ['nullable', fn ($attr, $value, $fail) => $value === 'none' || EmploymentStatus::whereKey($value)->exists() || $fail('The selected employment status is invalid.')],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'sex' => ['nullable', 'string', 'max:20'],
            'supervisor_id' => ['nullable', 'integer', 'exists:employees,id'],
            'status' => ['nullable', Rule::in(['all', ...Employee::STATUSES])],
            'joined_from' => ['nullable', 'date_format:Y-m-d'],
            'joined_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:joined_from'],
        ];
    }

    protected function applyEmployeeFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $search = $filters['search'];
                $q->where(fn ($w) => $w
                    ->where('e.emp_first_name', 'like', "%{$search}%")
                    ->orWhere('e.emp_last_name', 'like', "%{$search}%")
                    ->orWhere('e.employee_number', 'like', "%{$search}%"));
            })
            ->when(! empty($filters['department_id']), function ($q) use ($filters) {
                $ids = filter_var($filters['include_sub_departments'] ?? true, FILTER_VALIDATE_BOOLEAN)
                    ? $this->tree()->withDescendants((int) $filters['department_id'])
                    : [(int) $filters['department_id']];
                $q->whereIn('e.department_id', $ids);
            })
            ->when(! empty($filters['job_title_id']), fn ($q) => $filters['job_title_id'] === 'none'
                ? $q->whereNull('e.job_title_id')
                : $q->where('e.job_title_id', $filters['job_title_id']))
            ->when(! empty($filters['employment_status_id']), fn ($q) => $filters['employment_status_id'] === 'none'
                ? $q->whereNull('e.employment_status_id')
                : $q->where('e.employment_status_id', $filters['employment_status_id']))
            ->when(! empty($filters['location_id']), fn ($q) => $q->where('e.location_id', $filters['location_id']))
            ->when(! empty($filters['sex']), fn ($q) => $q->where('e.emp_sex', $filters['sex']))
            ->when(! empty($filters['supervisor_id']), fn ($q) => $q->where('e.supervisor_id', $filters['supervisor_id']))
            ->when(! empty($filters['status']) && $filters['status'] !== 'all', fn ($q) => $q->where('e.status', $filters['status']))
            ->when(! empty($filters['joined_from']), fn ($q) => $q->whereDate('e.joined_date', '>=', $filters['joined_from']))
            ->when(! empty($filters['joined_to']), fn ($q) => $q->whereDate('e.joined_date', '<=', $filters['joined_to']));
    }

    /**
     * Filtered employees joined with their current assignment labels.
     */
    protected function employeeBase(array $filters): Builder
    {
        return $this->applyEmployeeFilters(
            DB::table('employees as e')
                ->leftJoin('departments as d', 'd.id', '=', 'e.department_id')
                ->leftJoin('job_titles as j', 'j.id', '=', 'e.job_title_id')
                ->leftJoin('employment_statuses as es', 'es.id', '=', 'e.employment_status_id')
                ->leftJoin('locations as l', 'l.id', '=', 'e.location_id'),
            $filters
        );
    }

    /**
     * Headcount by one attribute, aggregated in the database.
     * $labelSql must be a plain column expression; nulls become "Not specified".
     */
    protected function countBy(array $filters, string $labelSql): array
    {
        return $this->employeeBase($filters)
            ->selectRaw("{$labelSql} as label, count(*) as count")
            ->groupByRaw($labelSql)
            ->orderByRaw('count(*) desc')
            ->orderByRaw($labelSql)
            ->get()
            ->map(fn ($r) => ['label' => $r->label ?? 'Not specified', 'count' => (int) $r->count])
            ->all();
    }

    protected static function personName(?string $last, ?string $first): ?string
    {
        $name = trim(($last ?? '').', '.($first ?? ''), ', ');

        return $name === '' ? null : $name;
    }

    protected static function locationLabel(?string $address, ?string $city): ?string
    {
        $label = trim(implode(', ', array_filter([$address, $city])));

        return $label === '' ? null : $label;
    }
}

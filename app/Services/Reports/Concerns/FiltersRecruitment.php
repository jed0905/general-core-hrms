<?php

namespace App\Services\Reports\Concerns;

use App\Models\Employee;
use App\Models\RecruitmentSource;
use App\Models\Vacancy;
use App\Services\Reports\DepartmentTree;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Filters and helpers shared by the recruitment reports, the recruitment
 * counterpart of FiltersEmployees. A recruitment record belongs to its
 * vacancy's department (vacancies.department_id), filtered with the same
 * DepartmentTree rules as every Core HR report (sub-departments optional).
 *
 * Reports are aggregate by default. Nothing here selects applicant names,
 * contact details, documents, notes, scores or comments.
 */
trait FiltersRecruitment
{
    protected ?DepartmentTree $tree = null;

    protected function tree(): DepartmentTree
    {
        return $this->tree ??= app(DepartmentTree::class);
    }

    /**
     * @param  array<int, string>  $keys
     */
    protected function recruitmentFilters(array $keys, array $defaults = []): array
    {
        $all = [
            'date_from' => fn () => ['key' => 'date_from', 'label' => 'From', 'type' => 'date', 'required' => true, 'default' => now()->startOfMonth()->toDateString()],
            'date_to' => fn () => ['key' => 'date_to', 'label' => 'To', 'type' => 'date', 'required' => true, 'default' => now()->toDateString()],
            'department_id' => fn () => ['key' => 'department_id', 'label' => 'Department', 'type' => 'select', 'options' => $this->tree()->options()],
            'include_sub_departments' => fn () => ['key' => 'include_sub_departments', 'label' => 'Include sub-departments', 'type' => 'boolean', 'default' => true],
            'vacancy_id' => fn () => ['key' => 'vacancy_id', 'label' => 'Vacancy', 'type' => 'select', 'options' => Vacancy::orderByDesc('id')->get(['id', 'vacancy_number', 'title'])
                ->map(fn ($v) => ['value' => $v->id, 'title' => "{$v->vacancy_number} · {$v->title}"])->all()],
            'hiring_manager_id' => fn () => ['key' => 'hiring_manager_id', 'label' => 'Hiring Manager', 'type' => 'select', 'options' => Employee::whereIn('id', Vacancy::whereNotNull('hiring_manager_id')->select('hiring_manager_id'))
                ->orderBy('emp_last_name')->get(['id', 'emp_first_name', 'emp_last_name'])->map(fn ($e) => ['value' => $e->id, 'title' => "{$e->emp_last_name}, {$e->emp_first_name}"])->all()],
            'recruitment_source_id' => fn () => ['key' => 'recruitment_source_id', 'label' => 'Source', 'type' => 'select', 'options' => [
                ['value' => 'none', 'title' => 'Not specified'],
                ...RecruitmentSource::orderBy('sort_order')->get(['id', 'name'])->map(fn ($s) => ['value' => $s->id, 'title' => $s->name])->all(),
            ]],
            'vacancy_status' => fn () => ['key' => 'vacancy_status', 'label' => 'Vacancy Status', 'type' => 'select', 'options' => collect(Vacancy::STATUSES)
                ->map(fn ($s) => ['value' => $s, 'title' => ucwords(str_replace('_', ' ', $s))])->all()],
        ];

        return collect($keys)->map(function ($key) use ($all, $defaults) {
            $filter = $all[$key]();
            if (array_key_exists($key, $defaults)) {
                $filter['default'] = $defaults[$key];
            }

            return $filter;
        })->all();
    }

    protected function recruitmentRules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'include_sub_departments' => ['nullable', 'boolean'],
            'vacancy_id' => ['nullable', 'integer', 'exists:vacancies,id'],
            'hiring_manager_id' => ['nullable', 'integer', 'exists:employees,id'],
            'recruitment_source_id' => ['nullable', fn ($attr, $value, $fail) => $value === 'none' || RecruitmentSource::whereKey($value)->exists() || $fail('The selected source is invalid.')],
            'vacancy_status' => ['nullable', Rule::in(Vacancy::STATUSES)],
        ];
    }

    /**
     * Department / vacancy / hiring manager / vacancy status, on a vacancies alias.
     */
    protected function scopeVacancies(Builder $query, array $filters, string $alias = 'v'): Builder
    {
        return $query
            ->when(! empty($filters['department_id']), function ($q) use ($filters, $alias) {
                $ids = filter_var($filters['include_sub_departments'] ?? true, FILTER_VALIDATE_BOOLEAN)
                    ? $this->tree()->withDescendants((int) $filters['department_id'])
                    : [(int) $filters['department_id']];
                $q->whereIn("{$alias}.department_id", $ids);
            })
            ->when(! empty($filters['vacancy_id']), fn ($q) => $q->where("{$alias}.id", (int) $filters['vacancy_id']))
            ->when(! empty($filters['hiring_manager_id']), fn ($q) => $q->where("{$alias}.hiring_manager_id", (int) $filters['hiring_manager_id']))
            ->when(! empty($filters['vacancy_status']), fn ($q) => $q->where("{$alias}.status", $filters['vacancy_status']));
    }

    /**
     * Applications (alias "a") joined to their vacancy ("v"), with the vacancy and source filters.
     */
    protected function applicationBase(array $filters): Builder
    {
        $query = DB::table('applications as a')->join('vacancies as v', 'v.id', '=', 'a.vacancy_id');

        return $this->scopeVacancies($query, $filters)
            ->when(! empty($filters['recruitment_source_id']), fn ($q) => $filters['recruitment_source_id'] === 'none'
                ? $q->whereNull('a.recruitment_source_id')
                : $q->where('a.recruitment_source_id', (int) $filters['recruitment_source_id']));
    }

    /**
     * Inclusive date range on a date or timestamp column: from 00:00 on the
     * first day up to (not including) 00:00 the day after the last. Same
     * meaning as whereDate(>=, <=), but written as a plain range so the
     * column's index can be used.
     */
    protected function inPeriod(Builder $query, string $column, array $filters): Builder
    {
        return $query
            ->when(! empty($filters['date_from']), fn ($q) => $q->where($column, '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($q) => $q->where($column, '<', Carbon::parse($filters['date_to'])->addDay()->toDateString()));
    }

    protected function countDistinct(Builder $query, string $column): int
    {
        return (int) (clone $query)->selectRaw("count(distinct {$column}) as c")->value('c');
    }

    /** Percentage, or "—" when there is nothing to divide by (never a misleading 0%). */
    protected static function pct(int|float $part, int|float $whole): string
    {
        return $whole > 0 ? number_format($part * 100 / $whole, 1).'%' : '—';
    }

    /** Whole days between two date/timestamps (by calendar date, never negative). */
    protected static function days(mixed $from, mixed $to): ?int
    {
        if (! $from || ! $to) {
            return null;
        }

        return max(0, (int) Carbon::parse(substr((string) $from, 0, 10))->diffInDays(Carbon::parse(substr((string) $to, 0, 10))));
    }

    /**
     * @return array{count: int, average: string, median: string, min: string, max: string}
     */
    protected static function dayStats(Collection $days): array
    {
        $days = $days->filter(fn ($d) => $d !== null)->sort()->values();
        $n = $days->count();
        if ($n === 0) {
            return ['count' => 0, 'average' => '—', 'median' => '—', 'min' => '—', 'max' => '—'];
        }
        $median = $n % 2 ? $days[intdiv($n, 2)] : ($days[$n / 2 - 1] + $days[$n / 2]) / 2;

        return [
            'count' => $n,
            'average' => number_format($days->avg(), 1),
            'median' => number_format($median, 1),
            'min' => (string) $days->first(),
            'max' => (string) $days->last(),
        ];
    }

    /** "Not specified" for empty group labels. */
    protected static function label(mixed $value): string
    {
        return ($value === null || $value === '') ? 'Not specified' : (string) $value;
    }

    protected static function personName(?string $last, ?string $first): ?string
    {
        $name = trim(($last ?? '').', '.($first ?? ''), ', ');

        return $name === '' ? null : $name;
    }

    protected function periodNote(array $filters, string $basis): string
    {
        return "Period {$filters['date_from']} to {$filters['date_to']} (inclusive), by {$basis}.";
    }
}

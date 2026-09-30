<?php

namespace App\Services\Reports;

use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

/**
 * One report = one class. The same filters, query and row mapping feed the
 * web table, Excel, PDF and print, so there is no second copy of the logic.
 *
 * A report can have rows (columns() + query()/rows()), summary sections
 * (summary()), or both.
 */
abstract class Report
{
    public const PDF_ROW_LIMIT = 2000;

    public const PER_PAGE_OPTIONS = [15, 25, 50, 100];

    /** URL/route key, e.g. "employee-masterlist". */
    abstract public static function key(): string;

    /** Category key from ReportRegistry::CATEGORIES. */
    abstract public static function category(): string;

    abstract public function title(): string;

    abstract public function description(): string;

    public static function permission(): string
    {
        return ReportRegistry::CATEGORIES[static::category()]['permission'];
    }

    /**
     * Filter schema for the UI: [key, label, type (text|select|date|boolean), options?, default?, required?].
     */
    public function filters(): array
    {
        return [];
    }

    /**
     * Validation rules for the filters.
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Columns shown/exported: key => label. May depend on the filters.
     */
    public function columns(array $filters = []): array
    {
        return [];
    }

    /**
     * Sortable columns: column key => SQL expression.
     */
    public function sortable(): array
    {
        return [];
    }

    /**
     * Aggregate sections: [['title' => .., 'columns' => [..], 'rows' => [[..]]]].
     */
    public function summary(array $filters): array
    {
        return [];
    }

    /**
     * Notes about what the report does and doesn't cover.
     */
    public function notes(): array
    {
        return [];
    }

    /**
     * Base query for row reports (already filtered and ordered).
     */
    public function query(array $filters): ?BuilderContract
    {
        return null;
    }

    /**
     * One output row, keyed like columns().
     */
    public function mapRow(mixed $row, array $filters): array
    {
        return (array) $row;
    }

    public function hasRows(array $filters = []): bool
    {
        return $this->columns($filters) !== [];
    }

    /**
     * Filters with defaults applied and unknown keys dropped.
     */
    public function normalize(array $input): array
    {
        $filters = [];

        foreach ($this->filters() as $filter) {
            $value = $input[$filter['key']] ?? null;
            $filters[$filter['key']] = ($value === null || $value === '') ? ($filter['default'] ?? null) : $value;
        }

        foreach (['sort', 'direction'] as $key) {
            if (! empty($input[$key])) {
                $filters[$key] = $input[$key];
            }
        }

        return $filters;
    }

    /**
     * Rules for the shared list parameters.
     */
    public function listRules(): array
    {
        return [
            'sort' => ['nullable', Rule::in(array_keys($this->sortable()))],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', Rule::in(self::PER_PAGE_OPTIONS)],
        ];
    }

    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->sorted($this->query($filters), $filters)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($row) => $this->mapRow($row, $filters));
    }

    /**
     * Every row, streamed in chunks (exports). Queries end with a unique key
     * in their ORDER BY, so chunks never skip or repeat rows.
     */
    public function rows(array $filters): iterable
    {
        foreach ($this->sorted($this->query($filters), $filters)->lazy(500) as $row) {
            yield $this->mapRow($row, $filters);
        }
    }

    protected function sorted(BuilderContract $query, array $filters): BuilderContract
    {
        $sortable = $this->sortable();

        if (empty($filters['sort']) || ! isset($sortable[$filters['sort']])) {
            return $query;
        }

        $query = $query->reorder()
            ->orderByRaw($sortable[$filters['sort']].' '.(($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc'));

        foreach ((array) $this->tiebreaker() as $column) {
            $query->orderBy($column);
        }

        return $query;
    }

    /**
     * Column(s) that make the order unique, appended after a user-chosen sort.
     */
    protected function tiebreaker(): string|array
    {
        return 'id';
    }

    /**
     * Display the filter value the way the UI shows it (option title).
     */
    public function describeFilters(array $filters): array
    {
        $described = [];

        foreach ($this->filters() as $filter) {
            $value = $filters[$filter['key']] ?? null;

            if ($value === null || $value === '' || $value === false) {
                continue;
            }

            if (($filter['type'] ?? null) === 'select') {
                $option = collect($filter['options'] ?? [])->firstWhere('value', $value);
                $value = $option['title'] ?? $value;
            } elseif (($filter['type'] ?? null) === 'boolean') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No';
            }

            $described[$filter['label']] = (string) $value;
        }

        return $described;
    }

    /**
     * Section helper: counts with a share of the total.
     *
     * @param  iterable<array{label: string, count: int}>  $counts
     */
    protected function countSection(string $title, iterable $counts, string $labelHeading): array
    {
        $counts = collect($counts);
        $total = max(1, $counts->sum('count'));

        return [
            'title' => $title,
            'columns' => [$labelHeading, 'Employees', '%'],
            'rows' => $counts->map(fn ($c) => [$c['label'], (int) $c['count'], round($c['count'] * 100 / $total, 1)])->values()->all(),
        ];
    }
}

<?php

namespace App\Services\Reports;

use App\Models\Department;
use Illuminate\Support\Collection;

/**
 * The department hierarchy (departments.parent_id), loaded once per request.
 */
class DepartmentTree
{
    private ?Collection $departments = null;

    private array $paths = [];

    public function all(): Collection
    {
        return $this->departments ??= Department::select('id', 'name', 'parent_id')->orderBy('name')->get()->keyBy('id');
    }

    /**
     * "Parent › Child › Grandchild".
     */
    public function path(?int $id): ?string
    {
        if ($id === null || ! $this->all()->has($id)) {
            return null;
        }

        if (isset($this->paths[$id])) {
            return $this->paths[$id];
        }

        $names = [];
        $seen = [];
        $current = $this->all()->get($id);

        while ($current && ! isset($seen[$current->id])) {
            $seen[$current->id] = true;
            array_unshift($names, $current->name);
            $current = $current->parent_id ? $this->all()->get($current->parent_id) : null;
        }

        return $this->paths[$id] = implode(' › ', $names);
    }

    /**
     * The department and every department below it.
     */
    public function withDescendants(int $id): array
    {
        $ids = [$id];
        $frontier = [$id];

        while ($frontier !== []) {
            $frontier = $this->all()->whereIn('parent_id', $frontier)->keys()->diff($ids)->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }

    /**
     * Select options ordered by path.
     */
    public function options(): array
    {
        return $this->all()->keys()
            ->map(fn ($id) => ['value' => $id, 'title' => $this->path($id)])
            ->sortBy('title')
            ->values()
            ->all();
    }
}

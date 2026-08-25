<?php

namespace App\Http\Filters;

use Illuminate\Database\Eloquent\Builder;

class DesignationsFilter
{
    protected $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    /**
     * Apply filters to the given query.
     */
    public function apply(Builder $query): Builder
    {
        $search = $this->request['search'] ?? null;
        $operatingUnit = $this->request['operating_unit'] ?? null;

        // 🔍 Search filter
        $query->when(
            $search,
            fn($query) =>
            $query->where('name', 'LIKE', "%{$search}%")
        );

        // Operating Unit Filter
        $query->when($operatingUnit, function ($query) use ($operatingUnit) {
            $query->whereHas('operatingUnit', function ($q) use ($operatingUnit) {
                $q->where('id', $operatingUnit);
            });
        });

        return $query;
    }
}

<?php

namespace App\Http\Filters;

use Illuminate\Database\Eloquent\Builder;

class PositionsFilter
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
        $salaryGrade = $this->request['salary_grade'] ?? null;
        $operatingUnit = $this->request['operating_unit'] ?? null;
        

        // 🔍 Search filter
        $query->when($search, function ($query) use ($search) {
            $query->whereHas('government_position', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%");
            });
        });

        // 📌 Plantilla Item Number filter
        $query->when($salaryGrade, function ($query) use ($salaryGrade) {
            $query->whereHas('salary_grade', function ($q) use ($salaryGrade) {
                $q->where('id', $salaryGrade);
            });
        });

        // Operating Unit Filter
        $query->when($operatingUnit, function ($query) use ($operatingUnit) {
            $query->whereHas('operating_unit', function ($q) use ($operatingUnit) {
                $q->where('id', $operatingUnit);
            });
        });

        return $query;
    }
}

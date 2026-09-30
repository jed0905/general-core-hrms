<?php

namespace App\Services\Reports\Employee;

use App\Services\Reports\Concerns\FiltersEmployees;
use App\Services\Reports\Report;

/**
 * Aggregates only: no employee names or identifiers.
 */
class EmployeeDemographicsReport extends Report
{
    use FiltersEmployees;

    private const AGE_GROUPS = [
        // label => [min age inclusive, max age exclusive]
        'Under 20' => [0, 20],
        '20–29' => [20, 30],
        '30–39' => [30, 40],
        '40–49' => [40, 50],
        '50–59' => [50, 60],
        '60 and over' => [60, 200],
    ];

    public static function key(): string
    {
        return 'employee-demographics';
    }

    public static function category(): string
    {
        return 'employee';
    }

    public function title(): string
    {
        return 'Employee Demographics';
    }

    public function description(): string
    {
        return 'Headcount by sex, age group, marital status, nationality and assignment. Counts only.';
    }

    public function filters(): array
    {
        return $this->employeeFilters(['department_id', 'include_sub_departments', 'location_id', 'status']);
    }

    public function rules(): array
    {
        return $this->employeeFilterRules();
    }

    public function notes(): array
    {
        return ['Age is calculated from the date of birth as of today. Employees without a date of birth are counted as "Not specified".'];
    }

    public function summary(array $filters): array
    {
        return [
            $this->countSection('By Sex', collect($this->countBy($filters, 'e.emp_sex'))->map(fn ($c) => ['label' => ucfirst($c['label']), 'count' => $c['count']]), 'Sex'),
            $this->countSection('By Age Group', $this->ageGroups($filters), 'Age Group'),
            $this->countSection('By Marital Status', collect($this->countBy($filters, 'e.emp_marital_status'))->map(fn ($c) => ['label' => ucfirst($c['label']), 'count' => $c['count']]), 'Marital Status'),
            $this->countSection('By Nationality', $this->countByNationality($filters), 'Nationality'),
            $this->countSection('By Employment Status', $this->countBy($filters, 'es.name'), 'Employment Status'),
            $this->countSection('By Department', $this->countBy($filters, 'd.name'), 'Department'),
            $this->countSection('By Job Title', $this->countBy($filters, 'j.job_title'), 'Job Title'),
            $this->countSection('By Location', $this->countBy($filters, 'l.city'), 'Location'),
        ];
    }

    /**
     * Age buckets computed in SQL from birth-date cut-offs (portable across databases).
     */
    private function ageGroups(array $filters): array
    {
        $today = now()->startOfDay();
        $cases = [];
        $bindings = [];

        foreach (self::AGE_GROUPS as $label => [$min, $max]) {
            // age >= min  <=>  birthday <= today - min years; age < max  <=>  birthday > today - max years
            $cases[] = 'when e.emp_birthday <= ? and e.emp_birthday > ? then ?';
            array_push($bindings, $today->copy()->subYears($min)->toDateString(), $today->copy()->subYears($max)->toDateString(), $label);
        }

        $expression = 'case when e.emp_birthday is null then \'Not specified\' '.implode(' ', $cases).' else \'Not specified\' end';

        $counts = $this->employeeBase($filters)
            ->selectRaw("{$expression} as label, count(*) as count", $bindings)
            ->groupBy('label') // alias: the CASE carries bindings, so don't repeat it
            ->get()
            ->pluck('count', 'label');

        return collect([...array_keys(self::AGE_GROUPS), 'Not specified'])
            ->filter(fn ($label) => isset($counts[$label]))
            ->map(fn ($label) => ['label' => $label, 'count' => (int) $counts[$label]])
            ->values()
            ->all();
    }

    private function countByNationality(array $filters): array
    {
        return $this->employeeBase($filters)
            ->leftJoin('nationalities as n', 'n.id', '=', 'e.emp_nationality_id')
            ->selectRaw('n.name as label, count(*) as count')
            ->groupBy('n.name')
            ->orderByRaw('count(*) desc')
            ->get()
            ->map(fn ($r) => ['label' => $r->label ?? 'Not specified', 'count' => (int) $r->count])
            ->all();
    }
}

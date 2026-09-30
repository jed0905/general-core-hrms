<?php

namespace App\Services\Reports\Employee;

use App\Services\Reports\Concerns\FiltersEmployees;
use App\Services\Reports\Report;

/**
 * Current headcount only. Historical headcount is not offered: the movement
 * history can't yet reconstruct past states reliably.
 */
class HeadcountReport extends Report
{
    use FiltersEmployees;

    public static function key(): string
    {
        return 'headcount';
    }

    public static function category(): string
    {
        return 'employee';
    }

    public function title(): string
    {
        return 'Current Headcount';
    }

    public function description(): string
    {
        return 'Current workforce counts by record status, employment status, department, job title, sex and location.';
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
        return [
            'Current headcount as of '.now()->format('M j, Y g:i A').'.',
            'Historical headcount (as of a past date) is not available yet.',
        ];
    }

    public function summary(array $filters): array
    {
        // Record-status totals ignore the status filter so all states are visible.
        $allStatuses = array_merge($filters, ['status' => 'all']);

        return [
            $this->countSection('By Record Status', collect($this->countBy($allStatuses, 'e.status'))
                ->map(fn ($c) => ['label' => ucwords(str_replace('_', ' ', $c['label'])), 'count' => $c['count']]), 'Record Status'),
            $this->countSection('By Employment Status', $this->countBy($filters, 'es.name'), 'Employment Status'),
            $this->countSection('By Department', $this->countBy($filters, 'd.name'), 'Department'),
            $this->countSection('By Job Title', $this->countBy($filters, 'j.job_title'), 'Job Title'),
            $this->countSection('By Sex', collect($this->countBy($filters, 'e.emp_sex'))->map(fn ($c) => ['label' => ucfirst($c['label']), 'count' => $c['count']]), 'Sex'),
            $this->countSection('By Location', $this->countBy($filters, 'l.city'), 'Location'),
        ];
    }
}

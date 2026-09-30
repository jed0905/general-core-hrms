<?php

namespace App\Services\Reports\Employee;

use App\Models\EmployeeMovement;
use App\Models\EmployeeMovementType;
use App\Services\EmployeeMovementService;
use App\Services\Reports\DepartmentTree;
use App\Services\Reports\Report;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Validation\Rule;

/**
 * Built on the Employee Movement module (its list query and frozen
 * before/after snapshots). Filtering by movement type gives the promotion,
 * transfer, resignation, ... reports from this one report.
 */
class EmployeeMovementReport extends Report
{
    public static function key(): string
    {
        return 'employee-movements';
    }

    public static function category(): string
    {
        return 'employee';
    }

    public function title(): string
    {
        return 'Employee Movement';
    }

    public function description(): string
    {
        return 'Recorded employment changes with the assignment before and after. Filter by type for promotions, transfers, separations, etc.';
    }

    public function filters(): array
    {
        return [
            ['key' => 'search', 'label' => 'Employee', 'type' => 'text'],
            ['key' => 'movement_type_id', 'label' => 'Movement Type', 'type' => 'select', 'options' => EmployeeMovementType::orderBy('name')->get(['id', 'name', 'is_active'])
                ->map(fn ($t) => ['value' => $t->id, 'title' => $t->is_active ? $t->name : "{$t->name} (archived)"])->all()],
            ['key' => 'department_id', 'label' => 'Department (before or after)', 'type' => 'select', 'options' => app(DepartmentTree::class)->options()],
            ['key' => 'effective_from', 'label' => 'Effective from', 'type' => 'date'],
            ['key' => 'effective_to', 'label' => 'Effective to', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'select', 'default' => EmployeeMovement::STATUS_EFFECTIVE, 'options' => [
                ['value' => EmployeeMovement::STATUS_EFFECTIVE, 'title' => 'Effective'],
                ['value' => EmployeeMovement::STATUS_SCHEDULED, 'title' => 'Scheduled'],
                ['value' => EmployeeMovement::STATUS_CANCELLED, 'title' => 'Cancelled'],
                ['value' => 'all', 'title' => 'All'],
            ]],
        ];
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'movement_type_id' => ['nullable', 'integer', 'exists:employee_movement_types,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'effective_from' => ['nullable', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'status' => ['nullable', Rule::in([EmployeeMovement::STATUS_EFFECTIVE, EmployeeMovement::STATUS_SCHEDULED, EmployeeMovement::STATUS_CANCELLED, 'all'])],
        ];
    }

    public function columns(array $filters = []): array
    {
        return [
            'effective_date' => 'Effective',
            'employee_number' => 'Employee No.',
            'employee' => 'Employee',
            'movement' => 'Movement',
            'status' => 'Status',
            'from_department' => 'Department (before)',
            'to_department' => 'Department (after)',
            'from_job_title' => 'Job Title (before)',
            'to_job_title' => 'Job Title (after)',
            'from_employment_status' => 'Employment Status (before)',
            'to_employment_status' => 'Employment Status (after)',
            'from_location' => 'Location (before)',
            'to_location' => 'Location (after)',
            'from_supervisor' => 'Supervisor (before)',
            'to_supervisor' => 'Supervisor (after)',
            'from_record_status' => 'Record Status (before)',
            'to_record_status' => 'Record Status (after)',
            'reason' => 'Reason',
            'remarks' => 'Remarks',
        ];
    }

    public function sortable(): array
    {
        return ['effective_date' => 'effective_date'];
    }

    public function summary(array $filters): array
    {
        $counts = $this->query($filters)->reorder()
            ->selectRaw('movement_type_id, count(*) as count')
            ->groupBy('movement_type_id')
            ->pluck('count', 'movement_type_id');

        $names = EmployeeMovementType::whereIn('id', $counts->keys())->pluck('name', 'id');

        return [$this->countSection(
            'Movements by Type',
            $counts->map(fn ($count, $typeId) => ['label' => $names[$typeId] ?? "Type #{$typeId}", 'count' => $count])->sortBy('label')->values(),
            'Movement Type'
        )];
    }

    public function query(array $filters): Builder
    {
        $serviceFilters = $filters;

        if (($serviceFilters['status'] ?? null) === 'all') {
            unset($serviceFilters['status']);
        }

        return app(EmployeeMovementService::class)->query($serviceFilters)
            ->with(['employee:id,employee_number,emp_first_name,emp_last_name', 'type:id,name']);
    }

    public function mapRow(mixed $row, array $filters): array
    {
        $from = $row->snapshot['from'] ?? [];
        $to = $row->snapshot['to'] ?? [];

        return [
            'effective_date' => $row->effective_date?->toDateString(),
            'employee_number' => $row->employee?->employee_number,
            'employee' => $row->employee ? trim("{$row->employee->emp_last_name}, {$row->employee->emp_first_name}", ', ') : null,
            'movement' => $row->type?->name,
            'status' => match ($row->status) {
                EmployeeMovement::STATUS_EFFECTIVE => 'Effective',
                EmployeeMovement::STATUS_SCHEDULED => 'Scheduled',
                EmployeeMovement::STATUS_CANCELLED => 'Cancelled',
                default => $row->status,
            },
            'from_department' => $from['department'] ?? null,
            'to_department' => $to['department'] ?? null,
            'from_job_title' => $from['job_title'] ?? null,
            'to_job_title' => $to['job_title'] ?? null,
            'from_employment_status' => $from['employment_status'] ?? null,
            'to_employment_status' => $to['employment_status'] ?? null,
            'from_location' => $from['location'] ?? null,
            'to_location' => $to['location'] ?? null,
            'from_supervisor' => $from['supervisor'] ?? null,
            'to_supervisor' => $to['supervisor'] ?? null,
            'from_record_status' => $from['status'] ?? null,
            'to_record_status' => $to['status'] ?? null,
            'reason' => $row->reason,
            'remarks' => $row->remarks,
        ];
    }
}

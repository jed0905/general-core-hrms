<?php

namespace App\Exports;

use App\Models\EmployeeMovement;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Exports exactly the filtered query the movement list uses (chunked by Laravel Excel).
 */
class EmployeeMovementsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private Builder $query) {}

    public function query(): Builder
    {
        return $this->query->with([
            'employee:id,employee_number,emp_first_name,emp_last_name',
            'type:id,code,name',
        ]);
    }

    public function headings(): array
    {
        return [
            'Effective Date', 'Employee No.', 'Employee', 'Movement', 'Status',
            'Department (from)', 'Department (to)', 'Job Title (from)', 'Job Title (to)',
            'Employment Status (from)', 'Employment Status (to)', 'Location (from)', 'Location (to)',
            'Supervisor (from)', 'Supervisor (to)', 'Record Status (from)', 'Record Status (to)',
            'Reference', 'Reason', 'Remarks', 'Recorded At',
        ];
    }

    /**
     * @param  EmployeeMovement  $movement
     */
    public function map($movement): array
    {
        $from = $movement->snapshot['from'] ?? [];
        $to = $movement->snapshot['to'] ?? [];

        return [
            $movement->effective_date?->toDateString(),
            $movement->employee?->employee_number,
            trim(($movement->employee?->emp_last_name ?? '').', '.($movement->employee?->emp_first_name ?? ''), ', '),
            $movement->type?->name,
            match ($movement->status) {
                EmployeeMovement::STATUS_EFFECTIVE => 'Effective',
                EmployeeMovement::STATUS_SCHEDULED => 'Scheduled',
                EmployeeMovement::STATUS_CANCELLED => 'Cancelled',
                default => $movement->status,
            },
            $from['department'] ?? null, $to['department'] ?? null,
            $from['job_title'] ?? null, $to['job_title'] ?? null,
            $from['employment_status'] ?? null, $to['employment_status'] ?? null,
            $from['location'] ?? null, $to['location'] ?? null,
            $from['supervisor'] ?? null, $to['supervisor'] ?? null,
            $from['status'] ?? null, $to['status'] ?? null,
            $movement->reference_number,
            $movement->reason,
            $movement->remarks,
            $movement->created_at?->toDateTimeString(),
        ];
    }
}

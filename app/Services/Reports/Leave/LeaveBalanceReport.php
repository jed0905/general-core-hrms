<?php

namespace App\Services\Reports\Leave;

use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveType;
use App\Services\Reports\Concerns\FiltersEmployees;
use App\Services\Reports\Report;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * Current balances. "Available" comes from EmployeeLeaveBalance::available(),
 * the same rule used by filing and self-service.
 */
class LeaveBalanceReport extends Report
{
    use FiltersEmployees;

    public static function key(): string
    {
        return 'leave-balance';
    }

    public static function category(): string
    {
        return 'leave';
    }

    public function title(): string
    {
        return 'Leave Balance';
    }

    public function description(): string
    {
        return 'Current leave balances per employee and leave type.';
    }

    public function filters(): array
    {
        return [
            ...$this->employeeFilters(['search', 'department_id', 'include_sub_departments', 'status']),
            ['key' => 'leave_type_id', 'label' => 'Leave Type', 'type' => 'select', 'options' => LeaveType::orderBy('name')->get(['id', 'name'])->map(fn ($t) => ['value' => $t->id, 'title' => $t->name])->all()],
        ];
    }

    public function rules(): array
    {
        return array_merge($this->employeeFilterRules(), [
            'leave_type_id' => ['nullable', 'integer', 'exists:leave_types,id'],
        ]);
    }

    public function notes(): array
    {
        return ['Current balances only. Balance history is not available yet.'];
    }

    public function columns(array $filters = []): array
    {
        return [
            'employee_number' => 'Employee No.',
            'employee' => 'Employee',
            'department' => 'Department',
            'leave_type' => 'Leave Type',
            'balance' => 'Balance',
            'pending' => 'Pending',
            'available' => 'Available',
            'used' => 'Used',
            'as_of_date' => 'As of',
        ];
    }

    public function sortable(): array
    {
        return ['employee' => 'e.emp_last_name', 'leave_type' => 'lt.name', 'balance' => 'employee_leave_balances.balance', 'used' => 'employee_leave_balances.used'];
    }

    protected function tiebreaker(): string|array
    {
        return 'employee_leave_balances.id';
    }

    public function query(array $filters): Builder
    {
        return $this->applyEmployeeFilters(
            EmployeeLeaveBalance::query()
                ->join('employees as e', 'e.id', '=', 'employee_leave_balances.employee_id')
                ->join('leave_types as lt', 'lt.id', '=', 'employee_leave_balances.leave_type_id'),
            $filters
        )
            ->when(! empty($filters['leave_type_id']), fn ($q) => $q->where('employee_leave_balances.leave_type_id', $filters['leave_type_id']))
            ->select(['employee_leave_balances.*', 'e.employee_number', 'e.emp_last_name', 'e.emp_first_name', 'e.department_id', 'lt.name as leave_type_name'])
            ->orderBy('e.emp_last_name')
            ->orderBy('lt.name')
            ->orderBy('employee_leave_balances.id');
    }

    /**
     * @param  EmployeeLeaveBalance  $row
     */
    public function mapRow(mixed $row, array $filters): array
    {
        return [
            'employee_number' => $row->employee_number,
            'employee' => self::personName($row->emp_last_name, $row->emp_first_name),
            'department' => $this->tree()->path($row->department_id),
            'leave_type' => $row->leave_type_name,
            'balance' => (float) $row->balance,
            'pending' => (float) $row->pending,
            'available' => $row->available(),
            'used' => (float) $row->used,
            'as_of_date' => $row->as_of_date ? substr((string) $row->as_of_date, 0, 10) : null,
        ];
    }
}

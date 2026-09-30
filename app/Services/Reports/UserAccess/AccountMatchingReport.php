<?php

namespace App\Services\Reports\UserAccess;

use App\Models\User;
use App\Services\Reports\Concerns\FiltersEmployees;
use App\Services\Reports\Report;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Validation\Rule;

/**
 * Employees without a login, and logins not linked to an (existing) employee.
 */
class AccountMatchingReport extends Report
{
    use FiltersEmployees;

    private const EMPLOYEES = 'employees_without_accounts';

    private const ACCOUNTS = 'accounts_without_employees';

    public static function key(): string
    {
        return 'account-matching';
    }

    public static function category(): string
    {
        return 'user_access';
    }

    public function title(): string
    {
        return 'Employee / Account Matching';
    }

    public function description(): string
    {
        return 'Employees without user accounts, and user accounts without an employee record.';
    }

    public function filters(): array
    {
        return [
            ['key' => 'view', 'label' => 'Show', 'type' => 'select', 'default' => self::EMPLOYEES, 'options' => [
                ['value' => self::EMPLOYEES, 'title' => 'Employees without accounts'],
                ['value' => self::ACCOUNTS, 'title' => 'Accounts without employees'],
            ]],
            ...$this->employeeFilters(['status'], ['status' => 'active']),
        ];
    }

    public function rules(): array
    {
        return array_merge($this->employeeFilterRules(), [
            'view' => ['nullable', Rule::in([self::EMPLOYEES, self::ACCOUNTS])],
        ]);
    }

    public function notes(): array
    {
        return ['"Record Status" applies to the employees list only.'];
    }

    public function columns(array $filters = []): array
    {
        return ($filters['view'] ?? self::EMPLOYEES) === self::ACCOUNTS
            ? ['username' => 'Username', 'status' => 'Account Status', 'roles' => 'Roles', 'reason' => 'Why']
            : ['employee_number' => 'Employee No.', 'employee' => 'Employee', 'department' => 'Department', 'job_title' => 'Job Title', 'status' => 'Record Status'];
    }

    protected function tiebreaker(): string|array
    {
        return 'id';
    }

    public function summary(array $filters): array
    {
        return [[
            'title' => 'Matching',
            'columns' => ['', 'Count'],
            'rows' => [
                ['Employees without accounts'.(($filters['status'] ?? 'all') !== 'all' ? " ({$filters['status']})" : ''), $this->employeesWithoutAccounts($filters)->count()],
                ['Accounts without employees', $this->accountsWithoutEmployees()->count()],
            ],
        ]];
    }

    public function query(array $filters): Builder
    {
        return ($filters['view'] ?? self::EMPLOYEES) === self::ACCOUNTS
            ? $this->accountsWithoutEmployees()->with('roles:id,name')->select(['users.id', 'users.username', 'users.status', 'users.employee_id'])->orderBy('users.username')->orderBy('users.id')
            : $this->employeesWithoutAccounts($filters)->select(['e.id', 'e.employee_number', 'e.emp_last_name', 'e.emp_first_name', 'e.department_id', 'j.job_title', 'e.status'])->orderBy('e.emp_last_name')->orderBy('e.id');
    }

    private function employeesWithoutAccounts(array $filters): Builder
    {
        return $this->employeeBase($filters)
            ->whereNotExists(fn ($q) => $q->from('users')->whereColumn('users.employee_id', 'e.id'));
    }

    /**
     * Not linked at all, or linked to an employee id that no longer exists.
     */
    private function accountsWithoutEmployees(): Builder
    {
        return User::query()->where(fn ($q) => $q
            ->whereNull('users.employee_id')
            ->orWhereNotExists(fn ($e) => $e->from('employees')->whereColumn('employees.id', 'users.employee_id')));
    }

    public function mapRow(mixed $row, array $filters): array
    {
        if (($filters['view'] ?? self::EMPLOYEES) === self::ACCOUNTS) {
            return [
                'username' => $row->username,
                'status' => ucfirst((string) $row->status),
                'roles' => $row->roles->pluck('name')->sort()->implode(', '),
                'reason' => $row->employee_id ? 'Linked employee no longer exists' : 'Not linked',
            ];
        }

        return [
            'employee_number' => $row->employee_number,
            'employee' => self::personName($row->emp_last_name, $row->emp_first_name),
            'department' => $this->tree()->path($row->department_id),
            'job_title' => $row->job_title,
            'status' => $row->status,
        ];
    }
}

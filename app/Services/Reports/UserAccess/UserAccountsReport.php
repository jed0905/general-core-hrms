<?php

namespace App\Services\Reports\UserAccess;

use App\Models\User;
use App\Services\Reports\Report;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Account overview. Only explicitly selected, non-sensitive columns are read:
 * never password, remember_token, google_id, OTP data, IP or location.
 */
class UserAccountsReport extends Report
{
    public static function key(): string
    {
        return 'user-accounts';
    }

    public static function category(): string
    {
        return 'user_access';
    }

    public function title(): string
    {
        return 'User Accounts';
    }

    public function description(): string
    {
        return 'Login accounts with their status, linked employee, roles, two-factor setting and last successful login.';
    }

    public function filters(): array
    {
        return [
            ['key' => 'search', 'label' => 'Username or employee', 'type' => 'text'],
            ['key' => 'account_status', 'label' => 'Account Status', 'type' => 'select', 'options' => DB::table('users')->distinct()->orderBy('status')->pluck('status')
                ->map(fn ($s) => ['value' => $s, 'title' => ucfirst($s)])->all()],
            ['key' => 'role_id', 'label' => 'Role', 'type' => 'select', 'options' => Role::orderBy('name')->get(['id', 'name'])->map(fn ($r) => ['value' => $r->id, 'title' => $r->name])->all()],
            ['key' => 'linked', 'label' => 'Linked to employee', 'type' => 'select', 'options' => [
                ['value' => 'yes', 'title' => 'Linked'],
                ['value' => 'no', 'title' => 'Not linked'],
            ]],
        ];
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'account_status' => ['nullable', 'string', 'max:20'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'linked' => ['nullable', Rule::in(['yes', 'no'])],
        ];
    }

    public function columns(array $filters = []): array
    {
        return [
            'username' => 'Username',
            'status' => 'Status',
            'employee_number' => 'Employee No.',
            'employee' => 'Employee',
            'roles' => 'Roles',
            'two_factor' => '2FA',
            'last_login' => 'Last Successful Login',
        ];
    }

    public function sortable(): array
    {
        return ['username' => 'users.username', 'status' => 'users.status', 'last_login' => 'last_login'];
    }

    protected function tiebreaker(): string|array
    {
        return 'users.id';
    }

    public function summary(array $filters): array
    {
        $base = $this->base($filters);

        return [[
            'title' => 'Accounts',
            'columns' => ['', 'Accounts'],
            'rows' => [
                ['Total', (clone $base)->count()],
                ...(clone $base)->selectRaw('users.status, count(*) as count')->groupBy('users.status')->get()
                    ->map(fn ($r) => [ucfirst($r->status).' accounts', (int) $r->count])->all(),
                ['Linked to an employee', (clone $base)->whereNotNull('users.employee_id')->count()],
                ['Not linked to an employee', (clone $base)->whereNull('users.employee_id')->count()],
                ['Two-factor enabled', (clone $base)->where('users.is_two_factor_enabled', 1)->count()],
            ],
        ]];
    }

    public function query(array $filters): Builder
    {
        return $this->base($filters)
            ->with('roles:id,name')
            ->select(['users.id', 'users.username', 'users.status', 'users.is_two_factor_enabled', 'users.employee_id', 'e.employee_number', 'e.emp_last_name', 'e.emp_first_name'])
            ->selectSub(
                DB::table('auth_logs')
                    ->whereColumn('auth_logs.auth_id', 'users.id')
                    ->where('auth_logs.auth_type', 'login')
                    ->where('auth_logs.attempt_status', 'success')
                    ->selectRaw('max(auth_logs.created_at)'),
                'last_login'
            )
            ->orderBy('users.username')
            ->orderBy('users.id');
    }

    private function base(array $filters): Builder
    {
        return User::query()
            ->leftJoin('employees as e', 'e.id', '=', 'users.employee_id')
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $search = $filters['search'];
                $q->where(fn ($w) => $w
                    ->where('users.username', 'like', "%{$search}%")
                    ->orWhere('e.emp_first_name', 'like', "%{$search}%")
                    ->orWhere('e.emp_last_name', 'like', "%{$search}%")
                    ->orWhere('e.employee_number', 'like', "%{$search}%"));
            })
            ->when(! empty($filters['account_status']), fn ($q) => $q->where('users.status', $filters['account_status']))
            ->when(! empty($filters['role_id']), fn ($q) => $q->whereHas('roles', fn ($r) => $r->whereKey($filters['role_id'])))
            ->when(($filters['linked'] ?? null) === 'yes', fn ($q) => $q->whereNotNull('users.employee_id'))
            ->when(($filters['linked'] ?? null) === 'no', fn ($q) => $q->whereNull('users.employee_id'));
    }

    public function mapRow(mixed $row, array $filters): array
    {
        return [
            'username' => $row->username,
            'status' => ucfirst((string) $row->status),
            'employee_number' => $row->employee_number,
            'employee' => $row->employee_id ? trim("{$row->emp_last_name}, {$row->emp_first_name}", ', ') : null,
            'roles' => $row->roles->pluck('name')->sort()->implode(', '),
            'two_factor' => $row->is_two_factor_enabled ? 'On' : 'Off',
            'last_login' => $row->last_login ? substr((string) $row->last_login, 0, 16) : 'Never',
        ];
    }
}

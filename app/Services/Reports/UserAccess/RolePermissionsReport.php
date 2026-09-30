<?php

namespace App\Services\Reports\UserAccess;

use App\Services\Reports\Report;
use Illuminate\Contracts\Database\Query\Builder;
use Spatie\Permission\Models\Role;

class RolePermissionsReport extends Report
{
    public static function key(): string
    {
        return 'role-permissions';
    }

    public static function category(): string
    {
        return 'user_access';
    }

    public function title(): string
    {
        return 'Role / Permission Assignment';
    }

    public function description(): string
    {
        return 'Each role, how many users hold it, and its permissions.';
    }

    public function filters(): array
    {
        return [
            ['key' => 'role_id', 'label' => 'Role', 'type' => 'select', 'options' => Role::orderBy('name')->get(['id', 'name'])->map(fn ($r) => ['value' => $r->id, 'title' => $r->name])->all()],
            ['key' => 'permission', 'label' => 'Has permission', 'type' => 'text'],
        ];
    }

    public function rules(): array
    {
        return [
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'permission' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function columns(array $filters = []): array
    {
        return [
            'role' => 'Role',
            'users' => 'Users',
            'permission_count' => 'Permissions',
            'permissions' => 'Permission Names',
        ];
    }

    protected function tiebreaker(): string|array
    {
        return 'roles.id';
    }

    public function query(array $filters): Builder
    {
        return Role::query()
            ->withCount(['users', 'permissions'])
            ->with('permissions:id,name')
            ->when(! empty($filters['role_id']), fn ($q) => $q->whereKey($filters['role_id']))
            ->when(! empty($filters['permission']), fn ($q) => $q->whereHas('permissions', fn ($p) => $p->where('name', 'like', "%{$filters['permission']}%")))
            ->orderBy('roles.name')
            ->orderBy('roles.id');
    }

    public function mapRow(mixed $row, array $filters): array
    {
        return [
            'role' => $row->name,
            'users' => (int) $row->users_count,
            'permission_count' => (int) $row->permissions_count,
            'permissions' => $row->permissions->pluck('name')->sort()->implode(', '),
        ];
    }
}

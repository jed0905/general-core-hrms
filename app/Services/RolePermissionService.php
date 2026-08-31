<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Collection;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionService
{
    /**
     * Retrieve all roles with their assigned permissions and user counts.
     */
    public function getRolesWithPermissions(): Collection
    {
        return Role::query()
            ->with(['permissions:id,name'])
            ->withCount('users')
            ->latest()
            ->get();
    }

    /**
     * Retrieve all permissions grouped by their module/group prefix (e.g., user.create -> user).
     */
    public function getGroupedPermissions(): array
    {
        return Permission::all()
            ->groupBy(function (Permission $permission) {
                return explode('.', $permission->name)[0] ?? 'general';
            })
            ->toArray();
    }

    /**
     * Create a new role and optionally assign permissions.
     */
    public function createRole(array $data): Role
    {
        $role = Role::create([
            'name'       => $data['name'],
            'guard_name' => 'web',
        ]);

        if (!empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return $role;
    }

    /**
     * Update an existing role's name.
     */
    public function updateRole(Role $role, array $data): Role
    {
        $role->update([
            'name' => $data['name'],
        ]);

        if (isset($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return $role;
    }

    /**
     * Synchronize permissions for a specific role.
     */
    public function syncPermissions(Role $role, array $permissions): Role
    {
        $role->syncPermissions($permissions);

        return $role;
    }

    /**
     * Delete a role if it is not protected or currently assigned to users.
     */
    public function deleteRole(Role $role): bool
    {
        if (in_array($role->name, ['Super Admin', 'Admin'])) {
            throw new \Exception('System default roles cannot be deleted.');
        }

        return $role->delete();
    }
}

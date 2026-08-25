<?php

namespace App\Http\Controllers\Web\RolesAndPermissions;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Permission;

class RolePermissionsController extends Controller
{
    public function index(Request $request)
    {
        // $roles = Role::where('name', '!=', 'superadmin')->get();
        $roles = Role::all();
        $permissions = Permission::all();

        // Get role ID from request or default to first role's ID
        $selectedRoleId = $request->role_id ?? $roles->first()?->id;

        // Fetch the selected Role model
        $selectedRole = $selectedRoleId ? Role::with('permissions')->find($selectedRoleId) : null;

        $selectedRolePermissions = $selectedRole
            ? $selectedRole->permissions->pluck('id')->toArray()
            : [];

        // dd($permissions);
        return Inertia::render('app/RolesAndPermissions/RolePermissions/Index', [
            'roles' => $roles,
            'permissions' => $permissions,
            'selectedRoleId' => $selectedRoleId,
            'selectedRolePermissions' => $selectedRolePermissions
        ]);
    }

    public function attachPermission(Role $role, Permission $permission)
    {
        // dd("ATTACH");
        $role->givePermissionTo($permission);
        return redirect()->back();
    }

    public function detachPermission(Role $role, Permission $permission)
    {
        // dd("TEST");
        $role->revokePermissionTo($permission);
        return redirect()->back();
    }
}

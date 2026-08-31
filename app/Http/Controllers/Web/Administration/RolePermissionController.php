<?php

namespace App\Http\Controllers\Web\Administration;

use App\Http\Controllers\Controller;
use App\Services\RolePermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    public function __construct(
        protected RolePermissionService $rolePermissionService
    ) {}

    /**
     * Display a listing of roles along with grouped permissions for the matrix view.
     */
    public function index(): Response
    {
        return Inertia::render('app/Administration/RolePermission/Index', [
            'roles'       => $this->rolePermissionService->getRolesWithPermissions(),
            'permissions' => $this->rolePermissionService->getGroupedPermissions(),
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $this->rolePermissionService->createRole($validated);

        return redirect()->back()->with('success', 'Role created successfully.');
    }

    /**
     * Display detailed role information with permissions.
     */
    public function show(Role $role): Response
    {
        $role->load(['permissions', 'users:id,username']);

        return Inertia::render('app/Administration/Roles/Show', [
            'role'        => $role,
            'permissions' => $this->rolePermissionService->getGroupedPermissions(),
        ]);
    }

    /**
     * Update role details.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255', Rule::unique('roles')->ignore($role->id)],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $this->rolePermissionService->updateRole($role, $validated);

        return redirect()->back()->with('success', 'Role updated successfully.');
    }

    /**
     * Update permissions assigned to a role.
     */
    public function syncPermissions(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'permissions'   => ['present', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $this->rolePermissionService->syncPermissions($role, $validated['permissions']);

        return redirect()->back()->with('success', 'Role permissions updated successfully.');
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Role $role): RedirectResponse
    {
        try {
            $this->rolePermissionService->deleteRole($role);
            return redirect()->back()->with('success', 'Role deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}

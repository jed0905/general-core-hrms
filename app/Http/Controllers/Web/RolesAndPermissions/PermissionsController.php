<?php

namespace App\Http\Controllers\Web\RolesAndPermissions;

use App\Http\Requests\UpdatePermissionFormRequest;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Permission;
use App\Http\Requests\CreatePermissionFormRequest;

class PermissionsController extends Controller
{
    public function index()
    {
        $permissions = Permission::where('name', '!=', 'superadmin')->get();

        return Inertia::render('app/RolesAndPermissions/Permissions/Index', [
            'permissions' => $permissions,
        ]);
    }

    public function create()
    {
        return Inertia::render('app/RolesAndPermissions/Permissions/Create');
    }

    public function store(CreatePermissionFormRequest $request)
    {
        try {
            Permission::create($request->validated());
            return redirect()->route('permission.management.index')
                ->with('success', 'Permission created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    public function edit(string $id)
    {
        $permission = Permission::find($id);
        return Inertia::render('app/RolesAndPermissions/Permissions/Edit', [
            'permission' => $permission,
        ]);
    }

    public function update(UpdatePermissionFormRequest $request, Permission $id)
    {
        try {
            $id->update($request->validated());
            return redirect()->route('permission.management.index');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function destroy(string $id)
    {
        try {
            Permission::where('id', $id)->delete();
            return redirect()->route('permission.management.index');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}

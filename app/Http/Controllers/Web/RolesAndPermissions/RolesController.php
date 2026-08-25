<?php

namespace App\Http\Controllers\Web\RolesAndPermissions;

use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateRolesRequest;
use App\Http\Requests\CreateRoleFormRequest;
use App\Http\Requests\UpdateRoleFormRequest;

class RolesController extends Controller
{
    public function index()
    {
        $roles = Role::where('name', '!=', 'superadmin')->get();

        return Inertia::render('app/RolesAndPermissions/Roles/Index', [
            'roles' => $roles,
        ]);
    }

    public function create()
    {
        return Inertia::render('app/RolesAndPermissions/Roles/Create');
    }

    public function store(CreateRoleFormRequest $request)
    {
        try {
            Role::create($request->validated());
            return redirect()->route('role.management.create');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    public function edit(string $id)
    {
        $role = Role::find($id);
        return Inertia::render('app/RolesAndPermissions/Roles/Edit', [
            'role' => $role,
        ]);
    }

    public function update(UpdateRoleFormRequest $request, Role $id)
    {
        try {
            $id->update($request->validated());
            return redirect()->route('role.management.index');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function destroy(string $id)
    {
        try {
            Role::find($id)->delete();
            return redirect()->route('role.management.index');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}

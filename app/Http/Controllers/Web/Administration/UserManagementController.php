<?php

namespace App\Http\Controllers\Web\Administration;

use Exception;
use App\Models\User;
use Inertia\Inertia;
use App\Models\Employee;
use Illuminate\Http\Request;
use App\Services\UserService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\EmployeeResource;
use App\Http\Requests\StoreUserFormRequest;
use App\Http\Requests\UpdateUserFormRequest;

class UserManagementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(UserService $userService)
    {
        return Inertia::render('app/Administration/UserManagement/Index', [
            'users' => $userService->index(),
            'roles' => $userService->getRoles(),
            'operatingUnits' => $userService->getOperatingUnits(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(UserService $userService)
    {
        $data = $userService->create();

        return Inertia::render('app/Administration/UserManagement/Create', [
            'roles' => $data['roles'],
            'employees' => $data['employees'],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserFormRequest $request, UserService $userService)
    {

        try {
            $validated = $request->validated();

            $userService->store($validated);
            return redirect()->back();

            // return redirect()->route('administration.user.index');
        } catch (Exception $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => 'Failed to create user: ' . $e->getMessage()]);
        }

    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, string $id, UserService $userService)
    {
     
        if (!$request->hasValidSignature()) {
            abort(403, 'Invalid or expired link');
        }

        $data = $userService->edit($id);

        return Inertia::render('app/Administration/UserManagement/Edit', [
            'user' => $data['user'],
            'roles' => $data['roles'],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserFormRequest $request, string $id, UserService $userService)
    {
        try {

            $userService->update($request->validated(), $id);

            return redirect()->route('administration.user.index');

        } catch (Exception $e) {

            return redirect()->back()->withErrors(['error' => 'Failed to update user: ' . $e->getMessage()]);
        }

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, UserService $userService)
    {
        try {
            $userService->delete($id);

            return redirect()->route('administration.user.index');
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete user: ' . $e->getMessage()]);
        }
    }

    /**
     * Reset user password to default (Refer to env for the default password).
     */
    public function resetPassword(string $id, UserService $userService)
    {
        // Validate user exists
        try {

            $userService->adminResetUserPassword($id);

            return redirect()->route('administration.user.index');
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to reset password: ' . $e->getMessage()]);
        }
    }

}

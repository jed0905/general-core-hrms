<?php

namespace App\Services;

use App\Http\Filters\UserFilter;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\UserResource;
use App\Models\Employee;
use App\Models\OperatingUnit;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserService
{
    public function index()
    {
        $direction = request('direction') === 'Descending' ? 'DESC' : 'ASC';
        $user = Auth::user();
        
        // Prioritize request parameter if explicitly provided and not empty
        // For superadmin: leave operating unit null (no filter) unless explicitly provided in request
        // For other users: use request parameter if provided, otherwise use their own operating unit
        $requestOperatingUnit = request('operating_unit');
        
        if ($user->hasRole('superadmin')) {
            // Superadmin: only use operating unit if explicitly provided in request, otherwise null
            $userOperatingUnitId = ($requestOperatingUnit !== null && $requestOperatingUnit !== '') 
                ? $requestOperatingUnit 
                : null;
        } else {
            // Non-superadmin: use request parameter if provided, otherwise use their own operating unit
            $userOperatingUnitId = ($requestOperatingUnit !== null && $requestOperatingUnit !== '') 
                ? $requestOperatingUnit 
                : optional($user->employee)->operating_unit_id;
        }

        $employeeSearch = request('employee');

        $query = User::with('employee.personalInformation', 'roles')
            ->when(!$user->hasRole('superadmin'), function ($query) {
                // Prevent non-superadmin users from seeing superadmin users
                $query->whereDoesntHave('roles', function ($q) {
                    $q->where('name', 'superadmin');
                });
            })
            ->when($employeeSearch, function ($query) use ($employeeSearch) {
                $query->whereHas('employee.personalInformation', function ($q) use ($employeeSearch) {
                    $q->where('firstname', 'LIKE', '%' . $employeeSearch . '%')
                        ->orWhere('middlename', 'LIKE', '%' . $employeeSearch . '%')
                        ->orWhere('lastname', 'LIKE', '%' . $employeeSearch . '%');
                });
            })
            ->when($userOperatingUnitId, function ($query) use ($userOperatingUnitId) {
                $query->whereHas('employee', function ($q) use ($userOperatingUnitId) {
                    $q->where('operating_unit_id', $userOperatingUnitId);
                });
            })
            ->when(request('role'), function ($query) {
                $query->whereHas('roles', function ($q) {
                    $q->where('id', request('role'));
                });
            })
            ->when(request('account_status'), function ($query) {
                $query->where('status', request('account_status'));
            })
            ->orderBy('id', $direction)
            ->paginate(request('size', 10));

        $users = $query;
        foreach ($users->items() as $user) {
            $user->edit_link = URL::signedRoute('administration.user.edit', ['id' => $user->id]);
            $user->can_delete = Auth::id() !== $user->id;
        }

        return UserResource::collection($users);
    
    }

    public function getRoles(){
        $roles = Role::get();
        return $roles;
    }

    public function getOperatingUnits(){
        $operating_units = OperatingUnit::get();
        return $operating_units;
    }
    

    // Create page logic
    // Get Roles, Employees
    public function create()
    {
        $user = Auth::user();
        $operating_unit_id_of_authenticated_user = optional($user->employee)->operating_unit_id;

        // Get Roles
        $roles = Role::query()
            ->when(! auth()->user()->hasRole('superadmin'), function ($query) {
                // Exclude superadmin if the current user is not superadmin
                $query->where('name', '!=', 'superadmin');
            })
            ->get()
            ->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => Str::title(str_replace('_', ' ', $role->name)),
                ];
            });

        $employees = fn() => $user->hasRole('superadmin') ?
            EmployeeResource::collection(Employee::with('personalInformation')->get()) :
            EmployeeResource::collection(Employee::with('personalInformation')
                ->where('operating_unit_id', $operating_unit_id_of_authenticated_user)
                ->get());

        // Filter employee information to include only needed fields

        return [
            'roles' => $roles,
            'employees' => $employees,
        ];
    }

    // Saves a user
    public function store(array $validated)
    {
        DB::transaction(function () use ($validated) {
            $user = $user = User::create([
                'employee_id' => $validated['employeeId'],
                'username' => $validated['username'],
                'password' => Hash::make($validated['password']),
                'status' => $validated['status'],
            ]);

            $user->assignRole($validated['userRole']);

            return $user;
        });
    }

    // Edit page logic
    // Get user details
    public function edit(int $id)
    {
        $user = fn() => UserResource::make(
            User::with([
                'employee.personalInformation',
            ])
                ->findOrFail($id)
        );

        // Get Roles
        // Exclude superadmin role if the current user is not superadmin
        $roles = Role::query()
            ->when(! auth()->user()->hasRole('superadmin'), function ($query) {
                // Exclude superadmin if the current user is not superadmin
                $query->where('name', '!=', 'superadmin');
            })
            ->get()
            ->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => Str::title(str_replace('_', ' ', $role->name)),
                ];
            });

        return [
            'user' => $user(),
            'roles' => $roles,
        ];
    }

    // Updates user
    public function update(array $validated, int $id)
    {
        $user = User::findOrFail($id);

        // Detect changes
        // Compare status
        if ($validated['status'] !== $user->status) {
            $hasCriticalChange = true;
        }

        // Compare username
        if ($validated['username'] !== $user->username) {
            $hasCriticalChange = true;
        }

        // Compare role
        $newRoleId = $validated['userRole'];
        $currentRoleId = optional($user->roles->first())->id;

        if ($newRoleId != $currentRoleId) {
            $hasCriticalChange = true;
            $user->syncRoles([$newRoleId]);
        }

        // Compare password (if submitted)
        if (! empty($validated['password'])) {
            $hasCriticalChange = true;
            $validated['password'] = Hash::make($validated['password']);
            // dd(Hash::make($validated['password']));
        } else {
            unset($validated['password']);
        }

        DB::transaction(function () use ($user, $validated, $hasCriticalChange) {
            // Update user details
            $user->update($validated);

            // Update role if provided
            if (isset($validated['userRole'])) {
                $user->syncRoles([$validated['userRole']]);
            }

            // Invalidate all sessions if critical account data changed
            if ($hasCriticalChange) {
                DB::table('sessions')
                    ->where('user_id', $user->id)
                    ->delete(); // Logs the user out everywhere
            }

            return $user;
        });
    }

    // Deletes the user
    public function delete(int $id)
    {
        $user = User::findOrFail($id);

        DB::transaction(function () use ($user) {
            // Delete user
            $user->delete();

            DB::table('sessions')
                ->where('user_id', $user->id)
                ->delete(); // Logs the user out everywhere

            return $user;
        });
    }

    // Password is reset by the admin
    public function adminResetUserPassword(int $id)
    {
        $user = User::findOrFail($id);

        // Reset password to default
        $defaultPassword = config('app.default_password', 'password'); // Use a default password from config
        $user->password = Hash::make($defaultPassword);

        DB::transaction(function () use ($user) {
            $user->save();

            // Invalidate all sessions
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->delete(); // Logs the user out everywhere

            return $user;
        });
    }
}

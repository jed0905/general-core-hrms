<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserService
{
    /**
     * Retrieve paginated users with optional search and status filtering.
     */
    public function getPaginatedUsers(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return User::query()
            ->with(['employee:id,emp_first_name,emp_last_name,email', 'roles:id,name'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where('username', 'like', "%{$search}%")
                    ->orWhereHas('employee', function ($q) use ($search) {
                        $q->where('emp_first_name', 'like', "%{$search}%")
                            ->orWhere('emp_last_name', 'like', "%{$search}%");
                    });
            })
            ->when($filters['status'] ?? null, fn($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Get lookup options for dropdown selects (Employees & Roles).
     */
    public function getFormData(): array
    {
        return [
            'employees' => Employee::select('id', 'emp_first_name', 'emp_last_name')
                ->get()
                ->map(fn($emp) => [
                    'id'        => $emp->id,
                    'full_name' => "{$emp->emp_first_name} {$emp->emp_last_name}",
                ]),
            'roles' => Role::select('id', 'name')->get(),
        ];
    }

    /**
     * Create a new user account and assign optional roles.
     */
    public function createUser(array $data): User
    {
        $user = User::create([
            'username'              => $data['username'],
            'password'              => Hash::make($data['password']),
            'employee_id'           => $data['employee_id'] ?? null,
            'status'                => 'active',
            'failed_logins'         => 0,
            'is_two_factor_enabled' => 0,
        ]);

        if (!empty($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        return $user;
    }

    /**
     * Update existing user attributes.
     */
    public function updateUser(User $user, array $data): User
    {
        $user->update([
            'username'    => $data['username'],
            'employee_id' => $data['employee_id'] ?? null,
        ]);

        return $user;
    }

    /**
     * Deactivate user account.
     */
    public function deactivateUser(User $user): User
    {
        $user->update(['status' => 'inactive']);

        return $user;
    }

    /**
     * Activate user account and reset failed login attempts count.
     */
    public function activateUser(User $user): User
    {
        $user->update([
            'status'        => 'active',
            'failed_logins' => 0,
        ]);

        return $user;
    }

    /**
     * Reset user password and reset failed login attempts count.
     */
    public function resetPassword(User $user, string $password): User
    {
        $user->update([
            'password'      => Hash::make($password),
            'failed_logins' => 0,
        ]);

        return $user;
    }

    /**
     * Synchronize assigned roles for a user.
     */
    public function assignRoles(User $user, array $roles): User
    {
        $user->syncRoles($roles);

        return $user;
    }
}

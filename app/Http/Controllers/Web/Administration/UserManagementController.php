<?php

namespace App\Http\Controllers\Web\Administration;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * Display a paginated listing of users with search/status filters and form options.
     */
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status']);
        $formData = $this->userService->getFormData();

        return Inertia::render('app/Administration/UserManagement/Index', [
            'users'     => $this->userService->getPaginatedUsers($filters),
            'filters'   => $filters,
            'employees' => $formData['employees'],
            'roles'     => $formData['roles'],
        ]);
    }

    /**
     * Show form for creating a new user account.
     */
    public function create(): Response
    {
        $formData = $this->userService->getFormData();

        return Inertia::render('app/Administration/UserManagement/Create', [
            'employees' => $formData['employees'],
            'roles'     => $formData['roles'],
        ]);
    }

    /**
     * Store a newly created user account.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username'    => ['required', 'string', 'max:255', 'unique:users,username'],
            'password'    => ['required', Password::defaults()],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'roles'       => ['nullable', 'array'],
            'roles.*'     => ['string', 'exists:roles,name'],
        ]);

        $this->userService->createUser($validated);

        return redirect()->route('administration.user.index')
            ->with('success', 'User account created successfully.');
    }

    /**
     * Display detailed user information.
     */
    public function show(User $user): Response
    {
        $user->load(['employee', 'roles', 'permissions']);

        return Inertia::render('app/Administration/Users/Show', [
            'user' => $user,
        ]);
    }

    /**
     * Show form for editing user details.
     */
    public function edit(User $user): Response
    {
        $user->load(['roles']);
        $formData = $this->userService->getFormData();

        return Inertia::render('app/Administration/Users/Edit', [
            'user'      => $user,
            'employees' => $formData['employees'],
            'roles'     => $formData['roles'],
        ]);
    }

    /**
     * Update existing user attributes.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'username'    => ['required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
            'employee_id' => ['nullable', 'exists:employees,id'],
        ]);

        $this->userService->updateUser($user, $validated);

        return redirect()->back()->with('success', 'User details updated successfully.');
    }

    /**
     * Deactivate user account.
     */
    public function deactivate(User $user): RedirectResponse
    {
        $this->userService->deactivateUser($user);

        return redirect()->back()->with('success', 'User account deactivated successfully.');
    }

    /**
     * Activate user account and reset failed login attempts.
     */
    public function activate(User $user): RedirectResponse
    {
        $this->userService->activateUser($user);

        return redirect()->back()->with('success', 'User account activated successfully.');
    }

    /**
     * Reset user password.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $this->userService->resetPassword($user, $validated['password']);

        return redirect()->back()->with('success', 'Password reset successfully.');
    }

    /**
     * Update user roles using Spatie permission package.
     */
    public function assignRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'roles'   => ['required', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $this->userService->assignRoles($user, $validated['roles']);

        return redirect()->back()->with('success', 'User roles updated successfully.');
    }
}

<?php

namespace App\Http\Controllers\Web\People;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMyProfileRequest;
use App\Models\Employee;
use App\Services\EmployeeProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Employee self-service: the logged-in user's own employee record only.
 * The employee is always resolved from users.employee_id, never from input.
 */
class MyProfileController extends Controller
{
    public function __construct(protected EmployeeProfileService $profileService) {}

    public function show(Request $request): Response
    {
        $employee = $this->ownEmployee($request);

        if ($employee) {
            $this->authorize('view', $employee);
        }

        return Inertia::render('app/People/MyProfile/Index', [
            'profile' => $employee ? $this->profileService->getProfile($employee) : null,
            'can' => [
                'update' => $employee !== null && $request->user()->can('updateContactDetails', $employee),
            ],
        ]);
    }

    public function update(UpdateMyProfileRequest $request): RedirectResponse
    {
        $this->profileService->updateContactDetails($request->ownEmployee(), $request->validated());

        return redirect()->route('people.my-profile.show')->with('success', 'Contact details updated.');
    }

    private function ownEmployee(Request $request): ?Employee
    {
        $employeeId = $request->user()->employee_id;

        return $employeeId ? Employee::find($employeeId) : null;
    }
}

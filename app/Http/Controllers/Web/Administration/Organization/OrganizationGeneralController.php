<?php

namespace App\Http\Controllers\Web\Administration\Organization;

use App\Http\Controllers\Controller;
use App\Services\OrganizationService;
use App\Services\DepartmentService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class OrganizationGeneralController extends Controller
{
    public function __construct(
        protected OrganizationService $organizationService,
        protected DepartmentService $departmentService
    ) {}

    public function index(): Response
    {
        $orgData = $this->organizationService->getOrganizationData();

        return Inertia::render('app/Administration/Organization/Index', [
            'initialGeneral'     => $orgData['initialGeneral'],
            'initialLocations'   => $orgData['initialLocations'],
            'initialBranding'    => $orgData['initialBranding'],
            'initialDepartments' => $this->departmentService->getAllDepartments(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'     => ['nullable', 'string', 'max:255'],
            'phone'    => ['nullable', 'string', 'max:50'],
            'email'    => ['nullable', 'email', 'max:255'],
            'country'  => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'city'     => ['nullable', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'street1'  => ['nullable', 'string', 'max:255'],
            'street2'  => ['nullable', 'string', 'max:255'],
            'note'     => ['nullable', 'string'],
        ]);

        $this->organizationService->updateGeneralInfo($validated);

        return redirect()->back()->with('success', 'Organization details updated successfully.');
    }
}

<?php

namespace App\Http\Controllers\Web\Administration\Organization;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Services\DepartmentService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function __construct(
        protected DepartmentService $departmentService
    ) {}

    public function index(): Response
    {
        return Inertia::render('Administration/Organization/Departments/Index', [
            'departments' => $this->departmentService->getAllDepartments(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'shortcut'  => ['nullable', 'string', 'max:50'],
            'parent_id' => ['nullable', 'exists:departments,id'],
        ]);

        $this->departmentService->createDepartment($validated);

        return redirect()->back()->with('success', 'Department created successfully.');
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'shortcut'  => ['nullable', 'string', 'max:50'],
            'parent_id' => ['nullable', 'exists:departments,id', 'different:id'],
        ]);

        $this->departmentService->updateDepartment($department, $validated);

        return redirect()->back()->with('success', 'Department updated successfully.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        try {
            $this->departmentService->deleteDepartment($department);
            return redirect()->back()->with('success', 'Department deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}

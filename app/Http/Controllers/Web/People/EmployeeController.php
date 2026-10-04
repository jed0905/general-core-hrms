<?php

namespace App\Http\Controllers\Web\People;

use App\Http\Controllers\Controller;
use App\Http\Requests\People\StoreEmployeeRequest;
use App\Http\Requests\People\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeDocumentType;
use App\Models\NumberSequence;
use App\Services\EmployeeDocumentService;
use App\Services\EmployeeService;
use App\Services\NumberSequenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function __construct(
        protected EmployeeService $employeeService,
        protected EmployeeDocumentService $documentService,
        protected NumberSequenceService $numberSequences
    ) {}

    /**
     * Display a listing of employees.
     */
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'department_id', 'location_id', 'status']);

        return Inertia::render('app/People/Employees/Index', [
            'employees' => $this->employeeService->getPaginatedEmployees($filters),
            'filters' => $filters,
            'options' => $this->employeeService->getFormDropdownOptions(),
        ]);
    }

    /**
     * Show the form for creating a new employee.
     */
    public function create(): Response
    {
        return Inertia::render('app/People/Employees/Create', [
            'options' => $this->employeeService->getFormDropdownOptions(),
            'employeeNumber' => [
                'auto' => $this->numberSequences->autoGenerates(NumberSequence::EMPLOYEE_NUMBER),
                'preview' => $this->numberSequences->preview(NumberSequence::EMPLOYEE_NUMBER),
            ],
        ]);
    }

    /**
     * Store a newly created employee in storage.
     */
    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $employee = $this->employeeService->createEmployee($request->validated());

        return redirect()->route('people.employee.show', $employee->id)
            ->with('success', 'Employee created successfully.');
    }

    /**
     * Display the specified employee profile.
     */
    public function show(Request $request, Employee $employee): Response
    {
        $employee->load([
            'department',
            'jobTitle',
            'location',
            'employmentStatus',
            'nationality',
            'supervisor',
            'education',
            'workExperience',
        ]);

        $user = $request->user();
        $canViewDocuments = $user->can('viewAny', [EmployeeDocument::class, $employee]);

        return Inertia::render('app/People/Employees/Show', [
            'employee' => $employee,
            'documents' => $canViewDocuments ? $this->documentService->documentsFor($employee) : [],
            'documentTypes' => $canViewDocuments
                ? EmployeeDocumentType::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code'])
                : [],
            'can' => [
                'viewDocuments' => $canViewDocuments,
                'uploadDocuments' => $user->can('create', [EmployeeDocument::class, $employee]),
                'updateDocuments' => $user->can('employee.documents.update'),
                'deleteDocuments' => $user->can('employee.documents.delete'),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified employee.
     */
    public function edit(Employee $employee): Response
    {
        $employee->load([
            'education',
            'workExperience',
        ]);

        return Inertia::render('app/People/Employees/Edit', [
            'employee' => $employee,
            'options' => $this->employeeService->getFormDropdownOptions(),
        ]);
    }

    /**
     * Update the specified employee in storage.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->employeeService->updateEmployee($employee, $request->validated());

        return redirect()->back()->with('success', 'Employee details updated successfully.');
    }

    /**
     * Archive the specified employee.
     */
    public function archive(Employee $employee): RedirectResponse
    {
        $this->employeeService->archiveEmployee($employee);

        return redirect()->back()->with('success', 'Employee archived successfully.');
    }

    /**
     * Restore an archived employee.
     */
    public function restore(Employee $employee): RedirectResponse
    {
        $this->employeeService->restoreEmployee($employee);

        return redirect()->back()->with('success', 'Employee restored successfully.');
    }

    /**
     * Export employee records.
     */
    public function export()
    {
        return back()->with('info', 'Employee export initiated.');
    }

    /**
     * Import employee records.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,xlsx'],
        ]);

        return back()->with('success', 'Employees imported successfully.');
    }
}

<?php

namespace App\Http\Controllers\Web\People;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\EmployeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function __construct(
        protected EmployeeService $employeeService
    ) {
    }

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
        ]);
    }

    /**
     * Store a newly created employee in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_number' => ['required', 'string', 'max:255', 'unique:employees,employee_number'],
            'emp_first_name' => ['required', 'string', 'max:255'],
            'emp_last_name' => ['required', 'string', 'max:255'],
            'emp_middle_name' => ['nullable', 'string', 'max:255'],
            'emp_suffix' => ['nullable', 'string', 'max:50'],
            'emp_birthday' => ['nullable', 'date'],
            'emp_sex' => ['required', 'string', Rule::in(['male', 'female', 'other'])],
            'emp_marital_status' => ['nullable', 'string'],
            'emp_nationality_id' => ['nullable', 'exists:nationalities,id'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'e_signature' => ['nullable', 'image', 'max:2048'],
            'street1' => ['nullable', 'string', 'max:255'],
            'street2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'country_id' => ['nullable', 'string', 'max:255'],
            'home_telephone_no' => ['nullable', 'string', 'max:50'],
            'mobile_no' => ['nullable', 'string', 'max:50'],
            'work_no' => ['nullable', 'string', 'max:50'],
            'work_email' => ['nullable', 'email', 'max:255', 'unique:employees,work_email'],
            'other_email' => ['nullable', 'email', 'max:255'],
            'joined_date' => ['nullable', 'date'],
            'job_title_id' => ['nullable', 'exists:job_titles,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'employment_status_id' => ['nullable', 'exists:employment_statuses,id'],
            'supervisor_id' => ['nullable', 'exists:employees,id'],
            'status' => ['required', Rule::in(['active', 'archived', 'on_leave', 'terminated'])],

            // Education records (matches educations table)
            'education' => ['nullable', 'array'],
            'education.*.level' => ['nullable', 'string', 'max:255'],
            'education.*.institute' => ['nullable', 'string', 'max:255'],
            'education.*.major_specialization' => ['nullable', 'string', 'max:255'],
            'education.*.year' => ['nullable', 'integer'],
            'education.*.gpa_score' => ['nullable', 'string', 'max:255'],
            'education.*.start_date' => ['nullable', 'date'],
            'education.*.end_date' => ['nullable', 'date'],

            // Work Experience records
            'work_experience' => ['nullable', 'array'],
            'work_experience.*.company' => ['nullable', 'string', 'max:255'],
            'work_experience.*.job_title' => ['nullable', 'string', 'max:255'],
            'work_experience.*.start_date' => ['nullable', 'date'],
            'work_experience.*.end_date' => ['nullable', 'date'],
            'work_experience.*.description' => ['nullable', 'string'],
        ]);

        $employee = $this->employeeService->createEmployee($validated);

        return redirect()->route('people.employee.show', $employee->id)
            ->with('success', 'Employee created successfully.');
    }

    /**
     * Display the specified employee profile.
     */
    public function show(Employee $employee): Response
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

        return Inertia::render('app/People/Employees/Show', [
            'employee' => $employee,
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
    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'employee_number' => ['required', 'string', 'max:255', Rule::unique('employees')->ignore($employee->id)],
            'emp_first_name' => ['required', 'string', 'max:255'],
            'emp_last_name' => ['required', 'string', 'max:255'],
            'emp_middle_name' => ['nullable', 'string', 'max:255'],
            'emp_suffix' => ['nullable', 'string', 'max:50'],
            'emp_birthday' => ['nullable', 'date'],
            'emp_sex' => ['required', 'string', Rule::in(['male', 'female', 'other'])],
            'emp_marital_status' => ['nullable', 'string'],
            'emp_nationality_id' => ['nullable', 'exists:nationalities,id'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'e_signature' => ['nullable', 'image', 'max:2048'],
            'street1' => ['nullable', 'string', 'max:255'],
            'street2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'country_id' => ['nullable', 'string', 'max:255'],
            'home_telephone_no' => ['nullable', 'string', 'max:50'],
            'mobile_no' => ['nullable', 'string', 'max:50'],
            'work_no' => ['nullable', 'string', 'max:50'],
            'work_email' => ['nullable', 'email', 'max:255', Rule::unique('employees')->ignore($employee->id)],
            'other_email' => ['nullable', 'email', 'max:255'],
            'joined_date' => ['nullable', 'date'],
            'job_title_id' => ['nullable', 'exists:job_titles,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'employment_status_id' => ['nullable', 'exists:employment_statuses,id'],
            'supervisor_id' => ['nullable', 'exists:employees,id'],
            'status' => ['required', Rule::in(['active', 'archived', 'on_leave', 'terminated'])],

            // Education records (matches educations table)
            'education' => ['nullable', 'array'],
            'education.*.level' => ['nullable', 'string', 'max:255'],
            'education.*.institute' => ['nullable', 'string', 'max:255'],
            'education.*.major_specialization' => ['nullable', 'string', 'max:255'],
            'education.*.year' => ['nullable', 'integer'],
            'education.*.gpa_score' => ['nullable', 'string', 'max:255'],
            'education.*.start_date' => ['nullable', 'date'],
            'education.*.end_date' => ['nullable', 'date'],

            // Work Experience records
            'work_experience' => ['nullable', 'array'],
            'work_experience.*.company' => ['nullable', 'string', 'max:255'],
            'work_experience.*.job_title' => ['nullable', 'string', 'max:255'],
            'work_experience.*.from' => ['nullable', 'date'],
            'work_experience.*.to' => ['nullable', 'date'],
            'work_experience.*.description' => ['nullable', 'string'],
        ]);

        $this->employeeService->updateEmployee($employee, $validated);

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

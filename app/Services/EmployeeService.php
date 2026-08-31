<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\JobTitle;
use App\Models\Location;
use App\Models\Nationality;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EmployeeService
{
    /**
     * Get paginated employees with search and filters.
     */
    public function getPaginatedEmployees(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Employee::query()
            ->with([
                'department:id,name',
                'jobTitle:id,job_title',
                'location:id,address,city,province,zip_code',
                'employmentStatus:id,name',
                'supervisor:id,emp_first_name,emp_last_name',
            ])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('employee_number', 'like', "%{$search}%")
                        ->orWhere('emp_first_name', 'like', "%{$search}%")
                        ->orWhere('emp_last_name', 'like', "%{$search}%")
                        ->orWhere('work_email', 'like', "%{$search}%");
                });
            })
            ->when($filters['department_id'] ?? null, fn($q, $id) => $q->where('department_id', $id))
            ->when($filters['location_id'] ?? null, fn($q, $id) => $q->where('location_id', $id))
            ->when($filters['status'] ?? null, fn($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Get required dropdown options for employee creation and editing forms.
     */
    public function getFormDropdownOptions(): array
    {
        return [
            'departments' => Department::select('id', 'name')->get(),
            'job_titles' => JobTitle::select('id', 'job_title')->get(),
            'locations' => Location::select('id', 'city', 'province', 'address', 'is_main')
                ->get()
                ->map(fn($loc) => [
                    'id' => $loc->id,
                    'name' => collect([$loc->city, $loc->province])->filter()->join(', ') ?: ($loc->address ?: 'Location #' . $loc->id),
                ]),
            'employment_statuses' => EmploymentStatus::select('id', 'name')->get(),
            'nationalities' => Nationality::select('id', 'name')->get(),
            'supervisors' => Employee::select('id', 'emp_first_name', 'emp_last_name')
                ->where('status', 'active')
                ->get()
                ->map(fn($emp) => [
                    'id' => $emp->id,
                    'name' => "{$emp->emp_first_name} {$emp->emp_last_name}",
                ]),
        ];
    }

    /**
     * Store a new employee with uploaded media.
     */
    public function createEmployee(array $data): Employee
    {
        return DB::transaction(function () use ($data) {
            if (isset($data['photo']) && $data['photo'] instanceof \Illuminate\Http\UploadedFile) {
                $data['photo'] = $data['photo']->store('employees/photos', 'public');
            }

            if (isset($data['e_signature']) && $data['e_signature'] instanceof \Illuminate\Http\UploadedFile) {
                $data['e_signature_path'] = $data['e_signature']->store('employees/signatures', 'public');
            }

            return Employee::create($data);
        });
    }

    /**
     * Update an employee's details and manage media replacements.
     */
    public function updateEmployee(Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data) {
            if (isset($data['photo']) && $data['photo'] instanceof \Illuminate\Http\UploadedFile) {
                if ($employee->photo) {
                    Storage::disk('public')->delete($employee->photo);
                }
                $data['photo'] = $data['photo']->store('employees/photos', 'public');
            }

            if (isset($data['e_signature']) && $data['e_signature'] instanceof \Illuminate\Http\UploadedFile) {
                if ($employee->e_signature_path) {
                    Storage::disk('public')->delete($employee->e_signature_path);
                }
                $data['e_signature_path'] = $data['e_signature']->store('employees/signatures', 'public');
            }

            $employee->update($data);

            return $employee;
        });
    }

    /**
     * Archive (deactivate) an employee.
     */
    public function archiveEmployee(Employee $employee): bool
    {
        return $employee->update(['status' => 'archived']);
    }

    /**
     * Restore an archived employee.
     */
    public function restoreEmployee(Employee $employee): bool
    {
        return $employee->update(['status' => 'active']);
    }
}

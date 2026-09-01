<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\JobTitle;
use App\Models\Location;
use App\Models\Nationality;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
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
     * Store a new employee with uploaded media and nested records.
     */
    public function createEmployee(array $data): Employee
    {
        return DB::transaction(function () use ($data) {
            $education = Arr::pull($data, 'education', []);
            $workExperience = Arr::pull($data, 'work_experience', []);

            if (isset($data['photo']) && $data['photo'] instanceof \Illuminate\Http\UploadedFile) {
                $data['photo'] = $data['photo']->store('employees/photos', 'public');
            }

            if (isset($data['e_signature']) && $data['e_signature'] instanceof \Illuminate\Http\UploadedFile) {
                $data['e_signature_path'] = $data['e_signature']->store('employees/signatures', 'public');
            }

            $employee = Employee::create($data);

            $this->syncEducation($employee, $education);
            $this->syncWorkExperience($employee, $workExperience);

            return $employee;
        });
    }

    /**
     * Update an employee's details, manage media replacements, and sync nested records.
     */
    public function updateEmployee(Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data) {
            $education = Arr::pull($data, 'education', []);
            $workExperience = Arr::pull($data, 'work_experience', []);

            // Handle Photo upload & cleanup
            if (isset($data['photo']) && $data['photo'] instanceof \Illuminate\Http\UploadedFile) {
                if ($employee->photo) {
                    Storage::disk('public')->delete($employee->photo);
                }
                $data['photo'] = $data['photo']->store('employees/photos', 'public');
            } else {
                unset($data['photo']); // Prevents setting photo column to NULL
            }

            // Handle E-Signature upload & cleanup
            if (isset($data['e_signature']) && $data['e_signature'] instanceof \Illuminate\Http\UploadedFile) {
                if ($employee->e_signature_path) {
                    Storage::disk('public')->delete($employee->e_signature_path);
                }
                $data['e_signature_path'] = $data['e_signature']->store('employees/signatures', 'public');
            }
            unset($data['e_signature']); // Removes input key that isn't a database column

            $employee->update($data);

            $this->syncEducation($employee, $education);
            $this->syncWorkExperience($employee, $workExperience);

            return $employee;
        });
    }

    /**
     * Sync education records based on the educations schema.
     */
    protected function syncEducation(Employee $employee, array $education): void
    {
        $employee->education()->delete();

        $validEducation = collect($education)->filter(function ($item) {
            return !empty($item['institute']) || !empty($item['level']) || !empty($item['major_specialization']);
        })->toArray();

        if (!empty($validEducation)) {
            $employee->education()->createMany($validEducation);
        }
    }

    /**
     * Sync work experience records, filtering out empty entries.
     */
    protected function syncWorkExperience(Employee $employee, array $workExperience): void
    {
        $employee->workExperience()->delete();

        $validExperience = collect($workExperience)->filter(function ($item) {
            return !empty($item['company']) || !empty($item['job_title']);
        })->toArray();

        if (!empty($validExperience)) {
            $employee->workExperience()->createMany($validExperience);
        }
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

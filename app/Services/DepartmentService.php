<?php

namespace App\Services;

use App\Models\Department;
use Illuminate\Database\Eloquent\Collection;

class DepartmentService
{
    /**
     * Get all departments with parent relationships.
     */
    public function getAllDepartments(): Collection
    {
        return Department::with('parent')->get();
    }

    /**
     * Create a new department.
     */
    public function createDepartment(array $data): Department
    {
        return Department::create($data);
    }

    /**
     * Update an existing department.
     */
    public function updateDepartment(Department $department, array $data): Department
    {
        $department->update($data);
        return $department;
    }

    /**
     * Delete department safely (ensuring no sub-departments depend on it).
     */
    public function deleteDepartment(Department $department): bool
    {
        if ($department->children()->exists()) {
            throw new \Exception('Cannot delete a department that has sub-departments.');
        }

        return $department->delete();
    }
}

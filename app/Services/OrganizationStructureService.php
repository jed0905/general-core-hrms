<?php

namespace App\Services;

use App\Models\Department;
use App\Models\OperatingUnit;
use Illuminate\Support\Facades\Auth;

class OrganizationStructureService
{
    // Index page logic
    // Get organization structure
    public function index()
    {
        $user = Auth::user();
        $operating_unit_id_of_authenticated_user = optional($user->employee)->operating_unit_id;
        
        $operating_units = [];
        $departments = [];

        if($user->hasRole('superadmin') || $user->hasRole('hr_director')) {
            $operating_units = OperatingUnit::orderBy('prefix_id', 'asc')->get()->toArray();
            $departments = Department::orderBy('name', 'asc')->get()->toArray();
        } else {
            $operating_units = OperatingUnit::where('id', $operating_unit_id_of_authenticated_user)->orderBy('prefix_id', 'asc')->get()->toArray();
            $departments = Department::orderBy('name', 'asc')->get()->toArray();
        }

        return [
            'operating_units' => $operating_units,
            'departments' => $departments,
        ];

    }

    // Store Functions for Operating Units, Departments, and Sub Units
    public function storeOperatingUnit(array $operatingUnit)
    {
        $operating_unit = OperatingUnit::create($operatingUnit);

        return $operating_unit;
    }

    public function storeDepartment(array $department)
    {
        $department = Department::create($department);

        return $department;
    }

    // Update Functions for Operating Units, Departments, and Sub Units
    public function updateOperatingUnit(array $request, string $id)
    {
        $operating_unit = OperatingUnit::findOrFail($id);

        $operating_unit->update($request);

        return $operating_unit;
    }

    public function updateDepartment(array $request, string $id)
    {
        $department = Department::findOrFail($id);

        $department->update($request);

        return $department;

    }

    public function deleteDepartment(string $id)
    {
        $department = Department::findOrFail($id);

        $department->delete();

        return $department;
    }

    // Lazy loading methods
    public function getOperatingUnits()
    {
        return OperatingUnit::orderBy('prefix_id', 'asc')->get()->toArray();
    }

    public function getDepartmentsByOperatingUnit($operatingUnitId)
    {
        return Department::where('operating_unit_id', $operatingUnitId)
                        ->orderBy('name', 'asc')
                        ->get()
                        ->toArray();
    }

    public function getDepartmentsByParent($operatingUnitId, $parentId = null)
    {
        return Department::where('operating_unit_id', $operatingUnitId)
                        ->where('parent_id', $parentId)
                        ->orderBy('name', 'asc')
                        ->get()
                        ->toArray();
    }


    // API methods for fetching organization structure

    public function getOperatingUnitsForApi()
    {
        return OperatingUnit::orderBy('prefix_id', 'asc')->get()->toArray();
    }

    public function getDepartmentsForApi()
    {
        return Department::orderBy('name', 'asc')->get()->toArray();
    }

}

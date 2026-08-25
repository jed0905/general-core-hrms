<?php

namespace App\Http\Controllers;

use App\Models\EmployeeMovement;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Department;
use App\Models\Designation;
use App\Models\OperatingUnit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeMovementController extends Controller
{
    public function create()
    {
        return Inertia::render('EmployeeMovements/Create', [
            'movementTypes' => EmployeeMovement::getMovementTypes(),
            'statusOptions' => EmployeeMovement::getStatusOptions(),
            'employees' => Employee::with('personalInformation')->get(),
            'positions' => Position::all(),
            'departments' => Department::all(),
            'designations' => Designation::all(),
            'operatingUnits' => OperatingUnit::all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'movement_type' => 'required|in:' . implode(',', array_keys(EmployeeMovement::getMovementTypes())),
            'status' => 'required|in:' . implode(',', array_keys(EmployeeMovement::getStatusOptions())),
            'effective_date' => 'required|date',
            'reason' => 'nullable|string',
            'remarks' => 'nullable|string',
            'justification' => 'nullable|string',
            // Add other validation rules as needed
        ]);

        $validated['requested_by'] = auth()->id();

        EmployeeMovement::create($validated);

        return redirect()->route('employee-movements.index')
            ->with('success', 'Employee movement created successfully.');
    }

    public function index()
    {
        $movements = EmployeeMovement::with([
            'employee.personalInformation',
            'previousPosition',
            'newPosition',
            'previousDepartment',
            'newDepartment',
            'approvedBy'
        ])->latest()->paginate(10);

        return Inertia::render('EmployeeMovements/Index', [
            'movements' => $movements,
            'movementTypes' => EmployeeMovement::getMovementTypes(),
            'statusOptions' => EmployeeMovement::getStatusOptions(),
        ]);
    }

    public function edit(EmployeeMovement $employeeMovement)
    {
        return Inertia::render('EmployeeMovements/Edit', [
            'movement' => $employeeMovement->load([
                'employee.personalInformation',
                'previousPosition',
                'newPosition',
                'previousDepartment',
                'newDepartment',
                'previousDesignation',
                'newDesignation',
                'previousOperatingUnit',
                'newOperatingUnit',
            ]),
            'movementTypes' => EmployeeMovement::getMovementTypes(),
            'statusOptions' => EmployeeMovement::getStatusOptions(),
            'employees' => Employee::with('personalInformation')->get(),
            'positions' => Position::all(),
            'departments' => Department::all(),
            'designations' => Designation::all(),
            'operatingUnits' => OperatingUnit::all(),
        ]);
    }

    public function update(Request $request, EmployeeMovement $employeeMovement)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'movement_type' => 'required|in:' . implode(',', array_keys(EmployeeMovement::getMovementTypes())),
            'status' => 'required|in:' . implode(',', array_keys(EmployeeMovement::getStatusOptions())),
            'effective_date' => 'required|date',
            'reason' => 'nullable|string',
            'remarks' => 'nullable|string',
            'justification' => 'nullable|string',
            // Add other validation rules as needed
        ]);

        $employeeMovement->update($validated);

        return redirect()->route('employee-movements.index')
            ->with('success', 'Employee movement updated successfully.');
    }
} 
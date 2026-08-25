<?php

namespace App\Http\Controllers\Web\Maintenance;


use Inertia\Inertia;
use App\Models\Department;
use App\Models\OperatingUnit;
use App\Http\Controllers\Controller;
use App\Http\Resources\DepartmentsResource;
use App\Http\Requests\CreateDepartmentsRequest;
use App\Http\Requests\UpdateDepartmentFormRequest;

class MaintenanceDepartmentsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $departments = fn() => DepartmentsResource::collection(Department::with('operatingUnit')->get());
        return Inertia::render('app/Maintenance/Departments/Index', [
            'departments' => $departments,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $operatingUnits = OperatingUnit::get();
        return Inertia::render('app/Maintenance/Departments/Create', [
            'operatingUnits' => $operatingUnits,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateDepartmentsRequest $request)
    {
        try {
            Department::create($request->validated());
            return redirect()->route('maintenance.department.create');
        } catch (\Exception $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        }
    }



    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $operatingUnits = OperatingUnit::get();
        $department = Department::where('id', $id)->with('operatingUnit')->first();
        return Inertia::render('app/Maintenance/Departments/Edit', [
            'department' => $department,
            'operatingUnits' => $operatingUnits,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDepartmentFormRequest $request, Department $id)
    {
        try {
            $id->update($request->validated());
            return redirect()->route('maintenance.department.index');
        } catch (\Exception $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(String $id)
    {
        try {
            Department::where('id', $id)->delete();
            return redirect()->route('maintenance.department.index');
        } catch (\Exception $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        }
    }
}

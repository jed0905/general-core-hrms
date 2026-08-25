<?php

namespace App\Http\Controllers\Web\Maintenance;

use App\Http\Requests\StoreDesignationFormRequest;
use App\Http\Requests\UpdateDesignationFormRequest;
use Inertia\Inertia;
use App\Models\Department;
use App\Models\Designation;
use Illuminate\Http\Request;
use App\Models\OperatingUnit;
use App\Http\Controllers\Controller;
use App\Http\Resources\DepartmentResource;
use App\Http\Resources\DesignationsResource;

class MaintenanceDesignationsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $designations = fn() => DesignationsResource::collection(Designation::with('operatingUnit')->get());
        // dd($designations());
        return Inertia::render('app/Maintenance/Designations/Index', [
            'designations' => $designations,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $operatingUnits = OperatingUnit::all();
        return Inertia::render('app/Maintenance/Designations/Create', [
            'operatingUnits' => $operatingUnits,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDesignationFormRequest $request)
    {
        try {
            Designation::create($request->validated());
            return redirect()->route('maintenance.designation.create');
        } catch (\Exception $e) {
            return back()->withErrors(['message' => $e->getMessage()]);

        }

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $operatingUnits = OperatingUnit::get();
        $designation = Designation::where('id', $id)->with('operatingUnit')->first();
        // dd($designation);
        return Inertia::render('app/Maintenance/Designations/Edit', [
            'designation' => $designation,
            'operatingUnits' => $operatingUnits,
        ]);

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDesignationFormRequest $request, Designation $id)
    {
        try {
            $id->update($request->validated());
            return redirect()->route('maintenance.designation.index');
        } catch (\Exception $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            Designation::where('id', $id)->delete();
            return redirect()->route('maintenance.designation.index');
        } catch (\Exception $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        }
    }
}

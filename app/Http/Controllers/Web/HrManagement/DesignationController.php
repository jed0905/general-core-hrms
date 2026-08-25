<?php

namespace App\Http\Controllers\Web\HrManagement;

use App\Http\Requests\UpdateDesignationFormRequest;
use Exception;
use Inertia\Inertia;
use App\Models\Employee;
use App\Models\Designation;
use Illuminate\Http\Request;
use App\Models\OperatingUnit;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\Controller;
use App\Http\Filters\DesignationsFilter;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\StoreDesignationFormRequest;
use App\Http\Resources\DesignationsResource;

class DesignationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();

        $employee_operating_unit = Employee::where('id', $user->employee_id)->pluck('operating_unit_id')->first();

        $query = Designation::with([
            'operatingUnit',
            'employeeDesignations.employee.personalInformation',
        ]);

        $filter = new DesignationsFilter(request()->all());
        $query = $filter->apply($query);

        // Restrict if not superadmin/hr_director
        if (!($user->hasRole('superadmin') || $user->hasRole('hr_director'))) {
            $query->where('operating_unit_id', $employee_operating_unit);
        }
        $query->orderBy('id', request('direction') === 'Descending' ? 'DESC' : 'ASC');
        $designations = $query->paginate(request('size', 10))->through(function ($designation) {
            $designation->edit_link = URL::signedRoute('hrmanagement.jobstructure.designation.edit', ['id' => $designation->id]);

            return $designation;

        });

        $operating_units = OperatingUnit::all();

        return Inertia::render('app/HrManagement/JobStructure/Designation/Index', [
            'designations' => DesignationsResource::collection($designations),
            'operating_units' => $operating_units,
        ]);
    }

    public function toggleStatus(Request $request, Designation $designation)
    {
        $validated = $request->validate([
            'is_vsl' => ['required', 'boolean'],
        ]);

        $designation->update([
            'is_vsl' => $validated['is_vsl'],
        ]);

        return back()->with([
            'message' => 'Designation updated successfully.',
            'type' => 'success',
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $user = Auth::user();

        if ($user->hasRole('superadmin') || $user->hasRole('hr_director')) {
            $operating_units = OperatingUnit::all();
        } else {
            // Get operating unit id of user
            $operating_unit_id_of_user = Employee::where('id', $user->id)->pluck('operating_unit_id')->first();
            $operating_units = OperatingUnit::findOrFail($operating_unit_id_of_user);
        }

        return Inertia::render('app/HrManagement/JobStructure/Designation/Create', [
            'operating_units' => $operating_units,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDesignationFormRequest $request)
    {
        try {
            Designation::create($request->validated());

            return redirect()->route('hrmanagement.jobstructure.designation.index');
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to create designation: ' . $e->getMessage()]);
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
        $user = Auth::user();

        $designation = Designation::with(['operatingUnit', 'employeeDesignations.employee.personalInformation'])->where('id', $id)->first();

        if ($user->hasRole('superadmin') || $user->hasRole('hr_director')) {
            $operatingUnits = OperatingUnit::all();
        } else {
            // Get operating unit id of user
            $operating_unit_id_of_user = Employee::where('id', $user->id)->pluck('operating_unit_id')->first();
            $operatingUnits = OperatingUnit::findOrFail($operating_unit_id_of_user);
        }

        return Inertia::render('app/HrManagement/JobStructure/Designation/Edit', [
            'designation' => $designation,
            'operating_units' => $operatingUnits
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDesignationFormRequest $request, string $id)
    {
        try {
            Designation::findOrFail($id)->update($request->validated());

            return redirect()->route('hrmanagement.jobstructure.designation.index');
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update designation: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}

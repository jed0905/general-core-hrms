<?php

namespace App\Http\Controllers\Web\Maintenance;

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\OperatingUnit;
use App\Http\Controllers\Controller;
use App\Http\Filters\OperatingUnitsFilter;
use App\Http\Resources\OperatingUnitResource;
use App\Http\Resources\OperatingUnitsResource;
use App\Http\Requests\CreateOperatingUnitsRequest;
use App\Http\Requests\UpdateOperatingUnitsRequest;
use App\Http\Requests\UpdateOperatingUnitFormRequest;

class MaintenanceOperatingUnitsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $operatingUnits = fn() => OperatingUnitsResource::collection(OperatingUnitsFilter::get());
        return Inertia::render('app/Maintenance/OperatingUnits/Index', [
            'operatingUnits' => $operatingUnits,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('app/Maintenance/OperatingUnits/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateOperatingUnitsRequest $request)
    {

        try {

            OperatingUnit::create($request->validated());

            return redirect()->route('maintenance.operating-unit.create');

        } catch (\Exception $e) {

            return back()->withErrors(['message' => $e->getMessage()]);

        }
    }


    public function edit(String $id)
    {
        $operatingUnit = OperatingUnit::where('id', $id)->first();
        return Inertia::render('app/Maintenance/OperatingUnits/Edit', [
            'operatingUnit' => $operatingUnit,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOperatingUnitFormRequest $request, OperatingUnit $id)
    {
        // dd($request->validated());
        try {
            $id->update($request->validated());
            return redirect()->route('maintenance.operating-unit.index');
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
            OperatingUnit::where('id', $id)->delete();
            return redirect()->route('maintenance.operating-unit.index');
        } catch (\Exception $e) {
            // return redirect()->back()->withErrors(['message' => $e->getMessage()]);

            // dd($e->getCode());
            if ($e->getCode() === '23000') {
                // Optional: check for 1451 in the message if you want to be more specific
                if (str_contains($e->getMessage(), '1451')) {
                    return redirect()->back()->withErrors(['message' => 'Cannot delete this operating unit because it is used in departments.']);
                }
            }

            return back()->withErrors('error', 'An unexpected error occurred.');
        }
    }
}

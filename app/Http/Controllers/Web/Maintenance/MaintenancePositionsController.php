<?php

namespace App\Http\Controllers\Web\Maintenance;

use App\Http\Requests\UpdatePositionFormRequest;
use Inertia\Inertia;
use App\Models\Position;
use Illuminate\Http\Request;
use App\Models\OperatingUnit;
use App\Http\Controllers\Controller;
use App\Http\Filters\PositionsFilter;
use App\Http\Resources\PositionsResource;
use App\Http\Requests\StorePositionFormRequest;

class MaintenancePositionsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $positions = fn() => PositionsResource::collection(PositionsFilter::get());
        // dd($positions());
        return Inertia::render('app/Maintenance/Positions/Index', [
            'positions' => $positions,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $operatingUnits = OperatingUnit::all();
        return Inertia::render('app/Maintenance/Positions/Create', [
            'operatingUnits' => $operatingUnits
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePositionFormRequest $request)
    {
        try {
            Position::create($request->validated());
            return redirect()->route('maintenance.position.create');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
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
        $operatingUnits = OperatingUnit::all();
        $position = Position::where('id', $id)->first();
        return Inertia::render('app/Maintenance/Positions/Edit', [
            'operatingUnits' => $operatingUnits,
            'position' => $position
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePositionFormRequest $request, Position $id)
    {
        try {
            $id->update($request->validated());
            return redirect()->route('maintenance.positions.index');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            Position::where('id', $id)->delete();
            return redirect()->route('maintenance.positions.index');
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

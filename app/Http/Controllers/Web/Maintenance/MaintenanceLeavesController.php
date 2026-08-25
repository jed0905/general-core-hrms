<?php

namespace App\Http\Controllers\Web\Maintenance;

use Inertia\Inertia;
use App\Models\Leave;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLeaveRequest;
use App\Http\Requests\CreateLeaveFormRequest;
use App\Http\Requests\UpdateLeaveFormRequest;

class MaintenanceLeavesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $leaves = Leave::get();
        return Inertia::render('app/Maintenance/Leaves/Index', [
            'leaves' => $leaves
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('app/Maintenance/Leaves/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateLeaveFormRequest $request)
    {
        try {
            Leave::create($request->validated());
            return redirect()->route('maintenance.leave.create');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['message', $e->getMessage()]);
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
        $leave = Leave::where('id', $id)->first();
        return Inertia::render('app/Maintenance/Leaves/Edit', [
            'leave' => $leave
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateLeaveFormRequest $request, Leave $id)
    {
        try {
            $id->update($request->validated());
            return redirect()->route('maintenance.leaves.index');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['message', $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            Leave::where('id', $id)->delete();
            return redirect()->route('maintenance.leaves.index');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['message', $e->getMessage()]);
        }
    }
}

<?php

namespace App\Http\Controllers\Web\HrManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSpecialLeaveRequest;
use App\Http\Requests\UpdateSpecialLeaveRequest;
use App\Services\SpecialLeaveService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SpecialLeaveController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(SpecialLeaveService $specialLeaveService)
    {
        $specialLeaves = $specialLeaveService->getSpecialLeaves();
        return Inertia::render('app/HrManagement/Leave/SpecialLeaveType/Index', [
            'specialLeaves' => $specialLeaves
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {

        return Inertia::render('app/HrManagement/Leave/SpecialLeaveType/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSpecialLeaveRequest $request, SpecialLeaveService $specialLeaveService)
    {
        try{
            $specialLeaveService->storeSpecialLeave($request->validated());
            return redirect()->route('hrmanagement.leave.specialLeave.create');
        }catch(\Exception $e){
            return redirect()->route('hrmanagement.leave.specialLeave.index')->withErrors(['error' => $e->getMessage()]);
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
    public function edit(string $id, SpecialLeaveService $specialLeaveService)
    {
        $specialLeave = $specialLeaveService->getSpecialLeaveById($id);
        return Inertia::render('app/HrManagement/Leave/SpecialLeaveType/Edit', [
            'specialLeave' => $specialLeave
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSpecialLeaveRequest $request, string $id, SpecialLeaveService $specialLeaveService)
    {
        try{
            $specialLeaveService->updateSpecialLeave($request->validated());
            return redirect()->route('hrmanagement.leave.specialLeave.index');
        }catch(\Exception $e){
            return redirect()->route('hrmanagement.leave.specialLeave.index')->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, SpecialLeaveService $specialLeaveService)
    {
        try{
            $specialLeaveService->deleteSpecialLeave($id);
            return redirect()->route('hrmanagement.leave.specialLeave.index');
        }catch(\Exception $e){
            return redirect()->route('hrmanagement.leave.specialLeave.index')->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function bulkDestroy(Request $request, SpecialLeaveService $specialLeaveService)
    {

        try{
            $specialLeaveService->bulkDestroy($request->validate([
                'ids' => 'required|array',
                'ids.*' => 'required|exists:special_leaves,id',
            ]));
            return redirect()->route('hrmanagement.leave.specialLeave.index');
        }catch(\Exception $e){
            return redirect()->route('hrmanagement.leave.specialLeave.index')->withErrors(['error' => $e->getMessage()]);
        }
    }
}

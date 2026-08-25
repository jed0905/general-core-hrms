<?php

namespace App\Http\Controllers\Web\HrManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDailyShiftScheduleFormRequest;
use App\Http\Requests\UpdateDailyShiftScheduleFormRequest;
use App\Models\DailyShiftSchedule;
use App\Services\DailyShiftScheduleService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DailyShiftSchedulesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(DailyShiftScheduleService $dailyShiftScheduleService)
    {

       

        $dailyShiftSchedules = $dailyShiftScheduleService->getDailyShiftSchedules();
        return Inertia::render('app/HrManagement/JobStructure/WorkShifts/DailyShiftSchedules/Index', [
            'dailyShiftSchedules' => $dailyShiftSchedules,
            
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('app/HrManagement/JobStructure/WorkShifts/DailyShiftSchedules/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDailyShiftScheduleFormRequest $request, DailyShiftScheduleService $dailyShiftScheduleService)
    {  
       
        $existingSchedule = DailyShiftSchedule::where('day_of_week', $request->validated('day_of_week'))
        ->where('time_in', $request->validated('time_in'))
        ->where('time_out', $request->validated('time_out'))
        ->where('break_start', $request->validated('break_start'))
        ->where('break_end', $request->validated('break_end'))
        ->first();
        if($existingSchedule){
            return redirect()->back()->withErrors(['error' => 'Daily shift schedule already exists']);
        }

        try{
            $dailyShiftSchedule = $dailyShiftScheduleService->storeDailyShiftSchedule($request->validated());
            return redirect()->route('hrmanagement.jobstructure.dailyShiftSchedules.create');
        }catch(\Exception $e){
            return redirect()->back()->withErrors(['errors' => $e->getMessage()]);
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
    public function edit(string $id, DailyShiftScheduleService $dailyShiftScheduleService)
    {
        
        $dailyShiftSchedule = $dailyShiftScheduleService->getDailyShiftScheduleById($id);
        return Inertia::render('app/HrManagement/JobStructure/WorkShifts/DailyShiftSchedules/Edit', [
            'dailyShiftSchedule' => $dailyShiftSchedule,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDailyShiftScheduleFormRequest $request, string $id, DailyShiftScheduleService $dailyShiftScheduleService)
    {
        try{
            $dailyShiftScheduleService->updateDailyShiftSchedule($id, $request->validated());
            return redirect()->route('hrmanagement.jobstructure.dailyShiftSchedules.index');
        }catch(\Exception $e){
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, DailyShiftScheduleService $dailyShiftScheduleService)
    {
        try{
            $dailyShiftScheduleService->destroyDailyShiftSchedule($id);
            return redirect()->route('hrmanagement.jobstructure.dailyShiftSchedules.index');
        }catch(\Exception $e){
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}

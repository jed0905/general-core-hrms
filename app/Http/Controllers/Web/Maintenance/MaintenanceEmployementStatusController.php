<?php

namespace App\Http\Controllers\Web\Maintenance;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateEmployementStatusRequest;
use App\Http\Requests\UpdateEmployementStatusRequest;
use App\Models\EmployementStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MaintenanceEmployementStatusController extends Controller
{
    public function index(){
        $employementStatuses = EmployementStatus::all();
        return Inertia::render('app/Maintenance/EmployementStatuses/Index', [
            'employementStatuses' => $employementStatuses,
        ]);
    }

    public function create(){
        return Inertia::render('app/Maintenance/EmployementStatuses/Create');
    }

    public function store(CreateEmployementStatusRequest $request){
        try{
            EmployementStatus::create($request->validated());
            return redirect()->route('maintenance.employement-statuses.create');
        }catch(\Exception $e){
            return redirect()->back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    public function edit(String $id){
        $employementStatus = EmployementStatus::find($id);
        return Inertia::render('app/Maintenance/EmployementStatuses/Edit', [
            'employementStatus' => $employementStatus,
        ]);
    }
    
    public function update(UpdateEmployementStatusRequest $request, String $id){
        try{
            EmployementStatus::find($id)->update($request->validated());
            return redirect()->route('maintenance.employement-statuses.index');       
        }catch(\Exception $e){
            return redirect()->back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    public function destroy(String $id){
        try{
            EmployementStatus::find($id)->delete();
            return redirect()->route('maintenance.employement-statuses.index');
        }catch(\Exception $e){
            return redirect()->back()->withErrors(['message' => $e->getMessage()]);
        }
    }
    
}

<?php

namespace App\Http\Controllers\Web\HrManagement;

use App\Http\Controllers\Controller;
use App\Http\Filters\EmployeeLeaveSchedulerFilter;
use App\Http\Resources\EmployeeLeaveSchedulerResource;
use App\Models\Employee;
use App\Models\EmployeeLeaveScheduler;
use App\Models\JobStatus;
use App\Models\Leave;
use App\Models\OperatingUnit;
use App\Models\PersonalInformation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;

use function PHPSTORM_META\map;

class EmployeeLeaveSchedulerController extends Controller
{
    public function index()
    {
        
        $jobStatuses = JobStatus::whereNotIn('name', ['Contract of Service', 'Job Order', 'Part Time'])->get();
        $leaves = Leave::whereIn('name', ['Vacation Leave', 'Sick Leave'])->get();
        $employeeLeaveSchedulers = fn() => EmployeeLeaveSchedulerResource::collection(
            EmployeeLeaveSchedulerFilter::get()
        );
        $operatingUnits = OperatingUnit::get();

        return Inertia::render('app/HrManagement/Leave/LeaveScheduler/Index',[
            'jobStatuses' => $jobStatuses,
            'leaves' => $leaves,
            'employeeLeaveSchedulers' => $employeeLeaveSchedulers,
            'operatingUnits' => $operatingUnits,
        ]);
    }

    public function manage(Request $request){

    
        $leaveId = $request->input('leave_id');
        $authEmployee = auth()->user()->employee;
        if($request->input('operating_unit_id') && $request->input('operating_unit_id') !== 'all'){
            $operatingUnitId = $request->input('operating_unit_id');
            // dd($operatingUnitId);
        }else{
            $operatingUnitId = optional($authEmployee)->operatingUnit?->id;
            // dd($operatingUnitId);
        }
       
        $jobStatuses = JobStatus::whereNotIn('name', ['Contract of Service', 'Job Order', 'Part Time'])->get();
        $leaves = Leave::whereIn('name', ['Vacation Leave', 'Sick Leave'])->get();
        
        $operatingUnits = OperatingUnit::get();
        // Only fetch employees if leave_id is provided
       
        $employees = $leaveId ?  Employee::whereDoesntHave('employeeLeaveSchedulers', function($query) use ($leaveId) {
            $query->where('leave_id', $leaveId);
        })
        ->when($operatingUnitId, function($query) use ($operatingUnitId) {
            $query->where('operating_unit_id', $operatingUnitId);
        })
        ->when($request->input('operating_unit_id') && $request->input('operating_unit_id') !== 'all', function($query) use ($request) {
            $query->where('operating_unit_id', $request->input('operating_unit_id'));
        })
        ->when($request->input('job_status_id') && $request->input('job_status_id') !== 'all', function($query) use ($request) {
            $query->where('job_status_id', $request->input('job_status_id'));
        })
        ->when($request->input('employee_type') && $request->input('employee_type') !== 'all', function($query) use ($request) {
            $query->where('employee_type', $request->input('employee_type'));
        })
        ->with(['personalInformation', 'operatingUnit'])
        ->join('personal_information', 'employees.id', '=', 'personal_information.employee_id')
        ->orderBy('personal_information.lastname', 'ASC')
        ->select('employees.*')
        ->get(): [];
        

        // $employeeThatHasLeaveScheduler = [];
        // if($leaveId){
        $employeeThatHasLeaveScheduler = $leaveId ? Employee::whereHas('employeeLeaveSchedulers', function($query) use ($leaveId) {
            $query->where('leave_id', $leaveId);
        })
        ->when($operatingUnitId, function($query) use ($operatingUnitId) {
            $query->where('operating_unit_id', $operatingUnitId);
        })
        ->when($request->input('operating_unit_id') && $request->input('operating_unit_id') !== 'all', function($query) use ($request) {
            $query->where('operating_unit_id', $request->input('operating_unit_id'));
        })
        ->when($request->input('job_status_id') && $request->input('job_status_id') !== 'all', function($query) use ($request) {
            $query->where('job_status_id', $request->input('job_status_id'));
        })
        ->when($request->input('employee_type') && $request->input('employee_type') !== 'all', function($query) use ($request) {
            $query->where('employee_type', $request->input('employee_type'));
        })
        ->with(['personalInformation', 'operatingUnit'])
        ->join('personal_information', 'employees.id', '=', 'personal_information.employee_id')
        ->orderBy('personal_information.lastname', 'ASC')
        ->select('employees.*')
        ->get() : [];
        // }

        return Inertia::render('app/HrManagement/Leave/LeaveScheduler/Manage', [
            'leaves' => $leaves,
            'leaveId' => $leaveId,
            'jobStatusId' => $request->input('job_status_id'),
            'employeeType' => $request->input('employee_type'),
            'jobStatuses' => $jobStatuses,
            'employees' => $employees,
            'employeeThatHasLeaveScheduler' => $employeeThatHasLeaveScheduler,
            'operatingUnits' => $operatingUnits,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'leave_id' => 'required|exists:leaves,id',
            'run_day' => 'required|integer|min:1|max:31',
            'credits_to_add' => 'required|numeric|min:0',
            'employees_to_add' => 'required|array|min:1',
            'employees_to_add.*' => 'integer|exists:employees,id',
        ]);

        foreach ($validated['employees_to_add'] as $employeeId) {
            $record = EmployeeLeaveScheduler::firstOrCreate(
                [
                    'employee_id' => $employeeId,
                    'leave_id' => $validated['leave_id'],
                ],
                [
                    'run_day' => $validated['run_day'],
                    'credits_to_add' => $validated['credits_to_add'],
                ]
            );

            // If exists, update the latest run_day/credits_to_add to the provided values
            if (!$record->wasRecentlyCreated) {
                $record->update([
                    'run_day' => $validated['run_day'],
                    'credits_to_add' => $validated['credits_to_add'],
                ]);
            }
        }

        return redirect()
            ->route('hrmanagement.leave.scheduler.manage', [
                'leave_id' => $validated['leave_id'],
                'job_status_id' => $request->input('job_status_id'),
                'employee_type' => $request->input('employee_type'),
            ])
            ->with([
                'success' => 'Employees added to leave scheduler successfully.',
            ]);
    }

    public function remove(Request $request)
    {
        $validated = $request->validate([
            'leave_id' => 'required|exists:leaves,id',
            'employees_to_remove' => 'required|array|min:1',
            'employees_to_remove.*' => 'integer|exists:employees,id',
        ]);

        EmployeeLeaveScheduler::where('leave_id', $validated['leave_id'])
            ->whereIn('employee_id', $validated['employees_to_remove'])
            ->delete();

        return redirect()
            ->route('hrmanagement.leave.scheduler.manage', [
                'leave_id' => $validated['leave_id'],
                'job_status_id' => $request->input('job_status_id'),
                'employee_type' => $request->input('employee_type'),
            ])
            ->with([
                'success' => 'Employees removed from leave scheduler successfully.',
            ]);
    }

    public function edit(string $id)
    {
        $employeeLeaveScheduler = EmployeeLeaveScheduler::with(['employee.personalInformation','employee.department', 'employee.operatingUnit', 'employee.position.government_position', 'leave'])
        ->where('id', $id)->first();
        return Inertia::render('app/HrManagement/Leave/LeaveScheduler/Edit', [
            'employeeLeaveScheduler' => $employeeLeaveScheduler,
        ]);
    }

    public function update(Request $request, string $id)
    {
   
        $validated = $request->validate([
            'run_day' => 'required|integer|min:1|max:31',
            'credits_to_add' => 'required|numeric|min:0',
        ]);
        try {
            EmployeeLeaveScheduler::where('id', $id)->update($validated);
            return redirect()->route('hrmanagement.leave.scheduler.index');
        }catch(\Exception $e){
            return redirect()->back()->withErrors(['error' => 'Failed to update leave scheduler: ' . $e->getMessage()]);
        }
        

    }

    public function destroy(string $id)
    {
        try {
            EmployeeLeaveScheduler::where('id', $id)->delete();
            return redirect()->route('hrmanagement.leave.scheduler.index');
        }catch(\Exception $e){
            return redirect()->back()->withErrors(['error' => 'Failed to delete leave scheduler: ' . $e->getMessage()]);
        }
    }

    public function destroyAll(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:employee_leave_schedulers,id',
        ]);

        try {
            EmployeeLeaveScheduler::whereIn('id', $validated['ids'])->delete();
            return redirect()->route('hrmanagement.leave.scheduler.index')
                ->with('success', 'Selected leave schedulers deleted successfully.');
        } catch(\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete selected leave schedulers: ' . $e->getMessage()]);
        }
    }
}

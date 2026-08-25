<?php

namespace App\Http\Controllers\Web\HrManagement;

use App\Http\Resources\EmployeeResource;
use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\Employee;
use App\Models\JobStatus;
use App\Models\Department;
use Illuminate\Http\Request;
use App\Services\PrintDtrService;
use App\Models\PersonalInformation;
use App\Http\Controllers\Controller;
use App\Services\DailyTimeRecordService;
use App\Http\Resources\PersonalInformationResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\AttendanceLogEventTypes;
use App\Http\Requests\StoreAttendanceLogEventRequest;
use App\Services\LeaveService;

class DailyTimeRecordController extends Controller
{
    public function __construct(
        private DailyTimeRecordService $dailyTimeRecordService
    ){

    }

    public function index(DailyTimeRecordService $dailyTimeRecordService)
    {
        $employees = $dailyTimeRecordService->index();
        $job_statuses = JobStatus::all();
        $operatingUnits = $dailyTimeRecordService->getOperatingUnits();
        $operating_unit = request()->input('operating_unit');
        $departments = $operating_unit ?
            $dailyTimeRecordService->getDepartmentsByOperatingUnit($operating_unit)
            : [];
        $filter = fn() => request()->only('search', 'department', 'employment_status', 'employee_type', 'size', 'direction', 'operating_unit');

        return Inertia::render('app/HrManagement/DailyTimeRecord/Index', [
            'employees' => $employees,
            'job_statuses' => $job_statuses,
            'departments' => $departments,
            'operatingUnits' => $operatingUnits,
            'filter' => $filter,
        ]);
    }



    public function view(Request $request)
    {
        $monthSelected = (int) ($request->selectedMonth ?? Carbon::today()->month);
        $yearSelected = (int) ($request->selectedYear ?? Carbon::today()->year);
        $employee_id = $request->employee_id ?? $request->id;


        $employeeLeaves = $this->dailyTimeRecordService->employeeLeaveApplications($employee_id);
        $employeeAttendanceLogs = $this->dailyTimeRecordService
        ->getEmployeeAttendanceLogsEvents($employee_id, $monthSelected, $yearSelected);

        $data = $this->dailyTimeRecordService->employeeDailyTimeRecords($employee_id, $monthSelected, $yearSelected);

        $employee = Employee::with('personalInformation')->where('id', $employee_id)->first();

        return Inertia::render('app/HrManagement/DailyTimeRecord/View', [
            'employee' => new EmployeeResource($employee),
            'selectedMonth' => $monthSelected,
            'selectedYear' => $yearSelected,
            'timeSheetData' => $data['dtrArray'],
            'holidays' => $data['holidays'],
            'presentDays' => $data['presentDays'],
            'lateDays' => $data['lateDays'],
            'weekendDaysWorked' => $data['weekendDaysWorked'],
            'attendanceLogEventTypes' => AttendanceLogEventTypes::all(),
            'employeeLeaves' => $employeeLeaves,
            'employeeAttendanceLogs' => $employeeAttendanceLogs,
        ]);
    }

    public function update(Request $request, DailyTimeRecordService $dailyTimeRecordService){
        $validate = $request->validate([
            'rowId' => 'nullable|integer',
            'employee_id' => 'required|integer',
            'selected_date' => 'required|date',
            'check_in' => 'nullable|date_format:H:i',
            'break_out' => 'nullable|date_format:H:i',
            'break_in' => 'nullable|date_format:H:i',
            'check_out' => 'nullable|date_format:H:i',
        ]);

        if($validate['check_in'] == null && $validate['break_out'] == null && $validate['break_in'] == null && $validate['check_out'] == null){
            return redirect()->back()->with('error', 'Please fill in at least one time field');
        }

        $dailyTimeRecordService->updateTimeRecord($validate);

        return redirect()->back()->with('success', 'Time record updated successfully');
    }

    public function printDailyTimeRecord(Request $request, PrintDtrService $printDtrService)
    {
        $dtr = $printDtrService->printDailyTimeRecord($request);

        return $dtr->inline('dtr.pdf');

    }

    public function storeEvent(StoreAttendanceLogEventRequest $request, DailyTimeRecordService $dailyTimeRecordService)
    {
        try {
            $validated = $request->validated();
            $employeeId = $validated['employee_id'] ?? $request->employee_id;

            if (!$employeeId) {
                 return back()->with('error', 'Employee ID is required.');
            }

            $employee = Employee::findOrFail($employeeId);

            $event = $dailyTimeRecordService->storeAttendanceEvent(
                $employee,
                $validated,
                $request->file('documents', [])
            );

            return back()->with('success', 'Official event saved successfully.');
        } catch (\Exception $e) {
            Log::channel('error')->error('HR DailyTimeRecordController@storeEvent failed', [
                'message' => $e->getMessage(),
            ]);
            return back()->with('error', 'Failed to save event: ' . $e->getMessage());
        }
    }

    public function destroyEvent($id, DailyTimeRecordService $dailyTimeRecordService)
    {
        try {
            // In HR context, we might not need to strict check the employee
            // but the service function deleteAttendanceEvent expects an employee object.
            // We can find the event first to get the employee.
            $eventRecord = \App\Models\EmployeeAttendanceLogEvents::findOrFail($id);
            $employee = Employee::findOrFail($eventRecord->employee_id);

            $dailyTimeRecordService->deleteAttendanceEvent($employee, $id);

            return back()->with('success', 'Official event deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete event: ' . $e->getMessage());
        }
    }

    public function destroyDocument($id, DailyTimeRecordService $dailyTimeRecordService)
    {
        try {
            $docRecord = \App\Models\EmployeeAttendanceLogDocuments::findOrFail($id);
            $eventRecord = \App\Models\EmployeeAttendanceLogEvents::findOrFail($docRecord->attendance_log_event_id);
            $employee = Employee::findOrFail($eventRecord->employee_id);

            $dailyTimeRecordService->deleteAttendanceDocument($employee, $id);

            return back()->with('success', 'Document deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete document: ' . $e->getMessage());
        }
    }


}

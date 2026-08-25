<?php

namespace App\Http\Controllers\Web\SelfService;

use Carbon\Carbon;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\DailyTimeRecordService;
use App\Models\AttendanceLogEventTypes;
use App\Http\Requests\StoreAttendanceLogEventRequest;
use App\Services\MyLeavesService;

class MyDtrController extends Controller
{

    public function __construct(
        private DailyTimeRecordService $dailyTimeRecordService,
        private MyLeavesService $myLeavesService
    ) {
    }


    public function index(
        Request $request,
    ) {
        $employeeId = Auth::user()->employee_id;

        $monthSelected = (int) ($request->selectedMonth ?? Carbon::today()->month);
        $yearSelected = (int) ($request->selectedYear ?? Carbon::today()->year);

        // Daily time record data for the selected period
        $dtrData = $this->dailyTimeRecordService->employeeDailyTimeRecords(
            $employeeId,
            $monthSelected,
            $yearSelected
        );

        // Dynamic attendance log event types
        $eventTypes = AttendanceLogEventTypes::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        // Employee leave summary + recent applications
        $leaveSummary = $this->myLeavesService->index();
        $myLeaveApplications = $this->myLeavesService->myLeaveApplications();

        $data = [
            'selectedMonth' => $monthSelected,
            'selectedYear' => $yearSelected,
            'timeSheetData' => $dtrData['dtrArray'],
            'holidays' => $dtrData['holidays'],
            'presentDays' => $dtrData['presentDays'],
            'lateDays' => $dtrData['lateDays'],
            'weekendDaysWorked' => $dtrData['weekendDaysWorked'],
            'attendanceLogEventTypes' => $eventTypes,
            'leaveSummary' => $leaveSummary,
            'myLeaveApplications' => $myLeaveApplications,
        ];

        if ($request->wantsJson()) {
            return response()->json($data);
        }

        return Inertia::render('app/SelfService/MyDtr/Index', $data);
    }

    public function pullData()
    {
        $employee = Auth::user()->employee;
        $biometrics_id = $employee->biometrics_id;
        $records = $this->dailyTimeRecordService->pullData($biometrics_id);

        return response()->json([$records], 200);
    }

    /**
     * Store or update an out-of-office event (official travel/business) for a specific date
     * along with any supporting documents uploaded by the employee.
     */
    public function storeEvent(StoreAttendanceLogEventRequest $request)
    {

        try {
            Log::channel('input')->info('MyDtrController@storeEvent called', [
                'user_id' => Auth::id(),
                'date' => $request->input('date'),
                'att_log_event_type_id' => $request->input('att_log_event_type_id'),
            ]);

            $user = Auth::user();
            $employee = $user->employee;
            $validated = $request->validated();

            $event = $this->dailyTimeRecordService->storeAttendanceEvent(
                $employee,
                $validated,
                $request->file('documents', [])
            );

            Log::channel('output')->info('MyDtrController@storeEvent success', [
                'event_id' => $event->id,
                'employee_id' => $employee->id,
            ]);

            return back()->with('success', 'Out-of-office event saved successfully.');
        } catch (\Exception $e) {
            Log::channel('error')->error('MyDtrController@storeEvent failed', [
                'message' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Failed to save out-of-office event: ' . $e->getMessage(),
            ]);
        }
    }

    public function destroyEvent($id)
    {
        try {
            Log::channel('input')->info('MyDtrController@destroyEvent called', [
                'user_id' => Auth::id(),
                'event_id' => $id,
            ]);

            $user = Auth::user();
            $employee = $user->employee;

            $this->dailyTimeRecordService->deleteAttendanceEvent($employee, $id);

            Log::channel('output')->info('MyDtrController@destroyEvent success', [
                'event_id' => $id,
                'employee_id' => $employee->id,
            ]);

            return back()->with('success', 'Out-of-office event deleted successfully.');
        } catch (\Exception $e) {
            Log::channel('error')->error('MyDtrController@destroyEvent failed', [
                'message' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Failed to delete out-of-office event: ' . $e->getMessage(),
            ]);
        }
    }

    public function destroyDocument($id)
    {
        try {
            Log::channel('input')->info('MyDtrController@destroyDocument called', [
                'user_id' => Auth::id(),
                'document_id' => $id,
            ]);

            $user = Auth::user();
            $employee = $user->employee;

            $this->dailyTimeRecordService->deleteAttendanceDocument($employee, $id);

            Log::channel('output')->info('MyDtrController@destroyDocument success', [
                'document_id' => $id,
                'employee_id' => $employee->id,
            ]);

            return back()->with('success', 'Document deleted successfully.');
        } catch (\Exception $e) {
            Log::channel('error')->error('MyDtrController@destroyDocument failed', [
                'message' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Failed to delete document: ' . $e->getMessage(),
            ]);
        }
    }
}

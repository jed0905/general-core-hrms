<?php

namespace App\Http\Controllers\Web\HrManagement;

use App\Http\Controllers\Controller;
use App\Http\Resources\WeeklyShiftTemplateResource;
use App\Models\User;
use App\Models\WeeklyShiftTemplate;
use App\Services\EmployeeWorkShiftService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;

class EmployeeWorkShiftsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(EmployeeWorkShiftService $employeeWorkShiftService)
    {

        $weeklyShiftTemplates = WeeklyShiftTemplate::paginate(10);

        $weeklyShiftTemplates->getCollection()->transform(function ($weeklyShiftTemplate) {
            $weeklyShiftTemplate->manage_link = URL::signedRoute('hrmanagement.dailytimerecord.employeeWorkShifts.manage', ['id' => $weeklyShiftTemplate->id]);

            return $weeklyShiftTemplate;
        });

        $weeklyShiftTemplates = WeeklyShiftTemplateResource::collection($weeklyShiftTemplates);

        // Get user information for role-based filtering
        $user = auth()->user();
        $userOperatingUnit = $user->employee->operating_unit_id ?? null;
        $isAdmin = $user->hasRole('superadmin') || $user->hasRole('hr_director');

        // Get employees data for the toggle view (paginated with filters)
        $employees = $employeeWorkShiftService->getEmployeesPaginated();

        $employeeStatus = $employeeWorkShiftService->getEmployeeStatus();
        $operatingUnits = $employeeWorkShiftService->getOperatingUnits();
        $departments = $employeeWorkShiftService->getDepartments();

        return Inertia::render('app/HrManagement/DailyTimeRecord/EmployeeWorkShifts/Index', [

            'weeklyShiftTemplates' => $weeklyShiftTemplates,
            'employees' => $employees,
            'employeeStatus' => $employeeStatus,
            'operatingUnits' => $operatingUnits,
            'departments' => $departments,
            'userOperatingUnit' => $userOperatingUnit,
            'isAdmin' => $isAdmin,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function manage(string $id, EmployeeWorkShiftService $employeeWorkShiftService)
    {
        $user = auth()->user();
        $weeklyShiftTemplateId = $id;

        // Get employees without work shifts (left side)
        $employeesWithoutWorkShift = fn () => request()
            ? $employeeWorkShiftService->getEmployeesWithoutWorkShift()
            : [];
        // Get employees with the selected weekly shift template (right side)
        $employeesWithWorkShift = fn () => request()
        ? $employeeWorkShiftService->getEmployeesWithWeeklyShiftTemplate($weeklyShiftTemplateId)
        : [];

        $operating_units = $employeeWorkShiftService->getOperatingUnits();
        $employee_status = $employeeWorkShiftService->getEmployeeStatus();
        $weeklyShiftTemplate = $employeeWorkShiftService->getWeeklyShiftTemplateById($id);
        $departments = request('operatingUnitId') ? $employeeWorkShiftService->getDepartmentsByOperatingUnit(request('operatingUnitId')) : [];

        return Inertia::render('app/HrManagement/DailyTimeRecord/EmployeeWorkShifts/Manage', [
            'employeesWithoutWorkShift' => $employeesWithoutWorkShift,
            'employeesWithWorkShift' => $employeesWithWorkShift,
            'operating_units' => $operating_units,
            'employee_status' => $employee_status,
            'selectedWeeklyShiftTemplateId' => $weeklyShiftTemplateId,
            'weeklyShiftTemplate' => $weeklyShiftTemplate,
            'departments' => $departments,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function assign(Request $request, EmployeeWorkShiftService $employeeWorkShiftService)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'work_shift_id' => 'required|exists:weekly_shift_templates,id',
        ]);
        try {
            $employeeWorkShiftService->assignWorkShift($request->employee_id, $request->work_shift_id);

            return redirect()->route('hrmanagement.dailytimerecord.employeeWorkShifts.index');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }

    }

    /**
     * Assign employees to work shift.
     */
    public function assignEmployees(Request $request, EmployeeWorkShiftService $employeeWorkShiftService)
    {
        $request->validate([
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'exists:employees,id',
            'weekly_shift_template_id' => 'required|exists:weekly_shift_templates,id',
        ]);

        $employeeWorkShiftService->assignEmployeesToWorkShift(
            $request->employee_ids,
            $request->weekly_shift_template_id
        );

        return redirect()->back()->with('success', 'Employees assigned to work shift successfully.');
    }

    /**
     * Remove employees from work shift.
     */
    public function removeEmployees(Request $request, EmployeeWorkShiftService $employeeWorkShiftService)
    {
        $request->validate([
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'exists:employees,id',
        ]);

        $employeeWorkShiftService->removeEmployeesFromWorkShift($request->employee_ids);

        return redirect()->back()->with('success', 'Employees removed from work shift successfully.');
    }

    /**
     * Get departments by operating unit.
     */
    public function getDepartmentsByOperatingUnit(string $operatingUnitId, EmployeeWorkShiftService $employeeWorkShiftService)
    {
        $departments = $employeeWorkShiftService->getDepartmentsByOperatingUnit($operatingUnitId);

        return response()->json($departments);
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
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}

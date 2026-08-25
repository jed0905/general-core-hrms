<?php

namespace App\Http\Controllers\Web\HrManagement;

use App\Http\Controllers\Controller;
use App\Models\JobStatus;
use App\Services\SeparatedEmployeesService;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class SeparatedEmployeesController extends Controller
{
    public function __construct(private SeparatedEmployeesService $separatedEmployeesService) {}

    public function index()
    {
        $employees = $this->separatedEmployeesService->getEmployees();
        $roles = Role::where('name', '!=', 'superadmin')->get();
        $jobStatuses = JobStatus::get();
        $operatingUnits = $this->separatedEmployeesService->getOperatingUnits();

        $operating_unit = request()->input('operating_unit');
        $departments = $operating_unit ?
            $this->separatedEmployeesService->getDepartmentsByOperatingUnit($operating_unit)
            : [];

        $filter = fn () => request()->only('search', 'department', 'employment_status', 'employee_type', 'size', 'direction', 'operating_unit');

        return Inertia::render('app/HrManagement/SeparatedEmployees/Index', [
            'employees' => $employees,
            'roles' => $roles,
            'jobStatuses' => $jobStatuses,
            'operatingUnits' => $operatingUnits,
            'departments' => $departments,
            'filter' => $filter,
        ]);
    }
}

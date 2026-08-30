<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\Location;
use App\Models\User;
use App\Models\AttendanceDevice;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Display the HRMS dashboard.
     */
    public function index()
    {
        /*
        |--------------------------------------------------------------------------
        | KPI DATA
        |--------------------------------------------------------------------------
        */

        $kpiData = [
            'totalLocations' => Location::count(),

            'totalDepartments' => Department::count(),

            'totalActiveEmployees' => Employee::query()
                ->where('status', 'active')
                ->count(),

            'totalActiveUsers' => User::query()
                ->where('status', 'active')
                ->count(),
        ];


        /*
        |--------------------------------------------------------------------------
        | EMPLOYEES BY LOCATION
        |--------------------------------------------------------------------------
        */

        $employeesPerLocation = Location::query()
            ->leftJoin(
                'employees',
                'locations.id',
                '=',
                'employees.location_id'
            )
            ->where(function ($query) {
                $query
                    ->whereNull('employees.status')
                    ->orWhere('employees.status', 'active');
            })
            ->select(
                'locations.id',
                'locations.address',
                DB::raw('COUNT(employees.id) as employee_count')
            )
            ->groupBy(
                'locations.id',
                'locations.address'
            )
            ->orderByDesc('employee_count')
            ->get();


        /*
        |--------------------------------------------------------------------------
        | EMPLOYEES BY DEPARTMENT
        |--------------------------------------------------------------------------
        */

        $employeesPerDepartment = Department::query()
            ->leftJoin(
                'employees',
                'departments.id',
                '=',
                'employees.department_id'
            )
            ->where(function ($query) {
                $query
                    ->whereNull('employees.status')
                    ->orWhere('employees.status', 'active');
            })
            ->select(
                'departments.id',
                'departments.name as department',
                DB::raw('COUNT(employees.id) as employee_count')
            )
            ->groupBy(
                'departments.id',
                'departments.name'
            )
            ->orderByDesc('employee_count')
            ->get();


        /*
        |--------------------------------------------------------------------------
        | EMPLOYMENT STATUS
        |--------------------------------------------------------------------------
        |
        | This assumes employees.employment_status_id references
        | employment_statuses.id.
        |
        */

        $employeeStatusCounts = DB::table('employment_statuses')
            ->leftJoin(
                'employees',
                'employment_statuses.id',
                '=',
                'employees.employment_status_id'
            )
            ->where(function ($query) {
                $query
                    ->whereNull('employees.status')
                    ->orWhere('employees.status', 'active');
            })
            ->select(
                'employment_statuses.id',
                'employment_statuses.name as status',
                DB::raw('COUNT(employees.id) as employee_count')
            )
            ->groupBy(
                'employment_statuses.id',
                'employment_statuses.name'
            )
            ->orderByDesc('employee_count')
            ->get();


        /*
        |--------------------------------------------------------------------------
        | EMPLOYEES BY JOB TITLE
        |--------------------------------------------------------------------------
        */

        $jobTitleCounts = DB::table('job_titles')
            ->leftJoin(
                'employees',
                'job_titles.id',
                '=',
                'employees.job_title_id'
            )
            ->where(function ($query) {
                $query
                    ->whereNull('employees.status')
                    ->orWhere('employees.status', 'active');
            })
            ->select(
                'job_titles.id',
                'job_titles.job_title',
                DB::raw('COUNT(employees.id) as employee_count')
            )
            ->groupBy(
                'job_titles.id',
                'job_titles.job_title'
            )
            ->orderByDesc('employee_count')
            ->limit(10)
            ->get();


        /*
        |--------------------------------------------------------------------------
        | TODAY'S ATTENDANCE
        |--------------------------------------------------------------------------
        |
        | This section should eventually use your normalized attendance
        | architecture instead of depending directly on Hikvision or
        | ZKTeco.
        |
        */

        $today = now()->toDateString();

        $totalActiveEmployees = $kpiData['totalActiveEmployees'];

        /*
         * Replace these queries with your final attendance service
         * once the attendance architecture is implemented.
         */

        $present = 0;
        $late = 0;
        $absent = 0;
        $onLeave = 0;

        /*
        |--------------------------------------------------------------------------
        | LEAVE SUMMARY
        |--------------------------------------------------------------------------
        */

        /*
         * Count employees currently on approved leave.
         *
         * This assumes your leave architecture has:
         *
         * leave_applications
         * leave_application_dates
         * leave_application_status_histories
         *
         * Adjust the status column/value according to your final
         * leave implementation.
         */

        $onLeaveToday = DB::table('leave_application_dates')
            ->join(
                'leave_applications',
                'leave_application_dates.leave_application_id',
                '=',
                'leave_applications.id'
            )
            ->whereDate(
                'leave_application_dates.leave_date',
                $today
            )
            ->where(
                'leave_applications.status',
                'approved'
            )
            ->distinct('leave_applications.employee_id')
            ->count('leave_applications.employee_id');


        /*
        |--------------------------------------------------------------------------
        | PENDING LEAVE APPLICATIONS
        |--------------------------------------------------------------------------
        */

        $pendingApplications = LeaveApplication::query()
            ->where('status', 'pending')
            ->count();


        /*
        |--------------------------------------------------------------------------
        | LEAVE APPLICATIONS THIS MONTH
        |--------------------------------------------------------------------------
        */

        $leaveThisMonth = LeaveApplication::query()
            ->whereBetween(
                'created_at',
                [
                    now()->startOfMonth(),
                    now()->endOfMonth(),
                ]
            )
            ->count();


        /*
        |--------------------------------------------------------------------------
        | LEAVE TYPES
        |--------------------------------------------------------------------------
        */

        $leaveTypeData = LeaveType::query()
            ->leftJoin(
                'leave_applications',
                'leave_types.id',
                '=',
                'leave_applications.leave_type_id'
            )
            ->where(function ($query) {
                $query
                    ->whereNull('leave_applications.status')
                    ->orWhere(
                        'leave_applications.status',
                        'approved'
                    );
            })
            ->select(
                'leave_types.id',
                'leave_types.name',
                DB::raw('COUNT(leave_applications.id) as total')
            )
            ->groupBy(
                'leave_types.id',
                'leave_types.name'
            )
            ->orderByDesc('total')
            ->get();


        /*
        |--------------------------------------------------------------------------
        | LEAVE SUMMARY OBJECT
        |--------------------------------------------------------------------------
        */

        $leaveSummary = [
            'onLeaveToday' => $onLeaveToday,

            'pendingApplications' => $pendingApplications,

            'thisMonth' => $leaveThisMonth,

            'typeLabels' => $leaveTypeData
                ->pluck('name')
                ->values(),

            'typeValues' => $leaveTypeData
                ->pluck('total')
                ->map(fn($value) => (int) $value)
                ->values(),
        ];


        /*
        |--------------------------------------------------------------------------
        | SETUP PROGRESS
        |--------------------------------------------------------------------------
        */

        $setupItems = [
            [
                'name' => 'Organization Profile',
                'completed' => $this->organizationSetupComplete(),
            ],

            [
                'name' => 'Locations',
                'completed' => Location::exists(),
            ],

            [
                'name' => 'Departments',
                'completed' => Department::exists(),
            ],

            [
                'name' => 'Job Titles',
                'completed' => DB::table('job_titles')->exists(),
            ],

            [
                'name' => 'Employment Statuses',
                'completed' => DB::table('employment_statuses')->exists(),
            ],

            [
                'name' => 'Pay Grades',
                'completed' => DB::table('pay_grades')->exists(),
            ],

            [
                'name' => 'Leave Types',
                'completed' => LeaveType::exists(),
            ],

            [
                'name' => 'Leave Policies',
                'completed' => DB::table('leave_policies')->exists(),
            ],

            [
                'name' => 'Work Shifts',
                'completed' => DB::table('shifts')->exists(),
            ],

            [
                'name' => 'Holidays',
                'completed' => DB::table('holidays')->exists(),
            ],
        ];


        $setupCompleted = collect($setupItems)
            ->where('completed', true)
            ->count();

        $setupTotal = count($setupItems);

        $setupPercentage = $setupTotal > 0
            ? round(($setupCompleted / $setupTotal) * 100)
            : 0;


        $setupProgress = [
            'percentage' => $setupPercentage,

            'completed' => $setupCompleted,

            'total' => $setupTotal,

            'items' => $setupItems,
        ];


        /*
        |--------------------------------------------------------------------------
        | RECENT SYSTEM ACTIVITY
        |--------------------------------------------------------------------------
        |
        | This assumes you will eventually have an activity/audit log.
        | Until then, return an empty collection.
        |
        */

        $recentActivities = [];


        /*
        |--------------------------------------------------------------------------
        | RETURN DASHBOARD
        |--------------------------------------------------------------------------
        */

        return Inertia::render('app/Dashboard', [

            'kpiData' => $kpiData,

            'employeesPerLocation' => $employeesPerLocation,

            'employeesPerDepartment' => $employeesPerDepartment,

            'employeeStatusCounts' => $employeeStatusCounts,

            'jobTitleCounts' => $jobTitleCounts,

            'attendanceSummary' => [
                'present' => $present,
                'late' => $late,
                'absent' => $absent,
                'onLeave' => $onLeave,
            ],

            'leaveSummary' => $leaveSummary,

            'setupProgress' => $setupProgress,

            'recentActivities' => $recentActivities,
        ]);
    }


    /**
     * Determine whether the organization profile has been configured.
     */
    private function organizationSetupComplete(): bool
    {
        /*
         * Replace this with your actual organization model.
         *
         * Example:
         *
         * return Organization::query()->exists();
         */

        return false;
    }
}

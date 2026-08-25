<?php

namespace App\Http\Controllers;

use Auth;
use App\Models\User;
use Inertia\Inertia;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Department;
use App\Models\Designation;
use Illuminate\Http\Request;
use App\Models\OperatingUnit;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->hasRole('superadmin') || $user->hasRole('hr_director')) {
            return $this->superadminDashboard();
        } else if ($user->hasRole('employee')) {
            return $this->employeeDashboard();
        } else if ($user->hasRole('campus_hr')) {
            return $this->campusHrDashboard();
        } else {
            return redirect()->route('/');
        }
    }

    private function superadminDashboard()
    {
        $totalOperatingUnits = OperatingUnit::count();
        $totalDepartments = Department::count();
        $totalActiveEmployees = Employee::where('date_separated', null)->count();
        $totalActiveUsers = User::where('status', 'active')->count();

        $kpiData = [
            'totalOperatingUnits' => $totalOperatingUnits,
            'totalDepartments' => $totalDepartments,
            'totalActiveEmployees' => $totalActiveEmployees,
            'totalActiveUsers' => $totalActiveUsers,
        ];

        $employeesPerOperatingUnit = Employee::selectRaw('operating_unit_id, COUNT(*) as employee_count')
            ->whereNull('date_separated')
            ->groupBy('operating_unit_id')
            ->with('operatingUnit:id,name')
            ->get()
            ->map(function ($item) {
                return [
                    'operating_unit' => $item->operatingUnit ? $item->operatingUnit->name : 'N/A',
                    'employee_count' => $item->employee_count,
                ];
            });

        $employeeStatsPerOu = DB::table('employees')
            ->join('operating_units', 'employees.operating_unit_id', '=', 'operating_units.id')
            ->join('job_statuses', 'employees.job_status_id', '=', 'job_statuses.id')
            ->whereNull('employees.date_separated')
            ->groupBy(
                'operating_units.shortcut',
                'job_statuses.name'
            )
            ->select(
                'operating_units.shortcut as operating_unit',
                'job_statuses.name as job_status',

                DB::raw("SUM(CASE WHEN employees.employee_type = 'Teaching' THEN 1 ELSE 0 END) as teaching"),
                DB::raw("SUM(CASE WHEN employees.employee_type = 'Non-Teaching' THEN 1 ELSE 0 END) as non_teaching"),

                DB::raw('COUNT(employees.id) as total')
            )
            ->get();

        $degreesCount = DB::table('employees as e')
            ->join('graduate_studies as gs', 'e.id', '=', 'gs.employee_id')
            ->join('operating_units as ou', 'e.operating_unit_id', '=', 'ou.id')
            ->where('gs.highest_level', 'Graduated')
            ->where(function ($q) {
                $q->where('gs.degree_course', 'like', 'Master%')
                    ->orWhere('gs.degree_course', 'like', "Master's%")
                    ->orWhere('gs.degree_course', 'like', 'MS %')
                    ->orWhere('gs.degree_course', 'like', 'Doctor%')
                    ->orWhere('gs.degree_course', 'like', 'Doctorate%');
            })
            ->selectRaw("
                ou.shortcut AS operating_unit,
                CASE
                    WHEN gs.degree_course LIKE 'Master%'
                    OR gs.degree_course LIKE \"Master's%\"
                    OR gs.degree_course LIKE 'MS %'
                    THEN 'Master'
                    WHEN gs.degree_course LIKE 'Doctor%'
                    OR gs.degree_course LIKE 'Doctorate%'
                    THEN 'Doctorate'
                END AS degree_type,
                COUNT(*) as total
            ")
            ->groupBy('operating_unit', 'degree_type')
            ->orderBy('operating_unit')
            ->get();

        // dd($degreesCount);


        $operatingUnits = OperatingUnit::select('id', 'name', 'shortcut')
            ->get()
            ->map(fn($ou) => [
                'title' => $ou->name,
                'value' => $ou->shortcut,
            ]);

        $authUser = Auth::user();

        $userOperatingUnit = $authUser->employee
            ? $authUser->employee->operatingUnit->shortcut
            : null;

        $fiveYearsAgo = now()->year - 4;

        $employeesHiredPerYearPerOu = DB::table('employees')
            ->join('operating_units', 'employees.operating_unit_id', '=', 'operating_units.id')
            ->whereNull('employees.date_separated') // only active employees
            ->whereNotNull('employees.date_hired')  // ensure date_hired is not null
            ->whereYear('employees.date_hired', '>=', $fiveYearsAgo) // last 5 years
            ->groupBy('operating_units.shortcut', DB::raw('YEAR(employees.date_hired)'))
            ->select(
                'operating_units.shortcut as operating_unit',
                DB::raw('YEAR(employees.date_hired) as year'),
                DB::raw('COUNT(employees.id) as total_hired')
            )
            ->orderBy('year')
            ->get();

        // dd($employeesHiredPerYearPerOu);

        $years = $employeesHiredPerYearPerOu
            ->pluck('year')
            ->unique()
            ->sort()
            ->values()
            ->all();

        // 2️⃣ Get unique Operating Units
        $operatingUnits = collect($employeesHiredPerYearPerOu->pluck('operating_unit')->unique()->values());


        // 3️⃣ Build datasets for chart
        $datasets = $operatingUnits->map(function ($ou) use ($employeesHiredPerYearPerOu, $years) {
            $data = collect($years)->map(function ($year) use ($employeesHiredPerYearPerOu, $ou) {
                $row = $employeesHiredPerYearPerOu->first(fn($i) => $i->operating_unit === $ou && $i->year == $year);
                return $row ? $row->total_hired : 0;
            });

            // Generate random color for each OU (optional)
            $colors = [
                'CA' => '#1976D2',
                'MLUC' => '#26A69A',
                'SLUC' => '#FFC107',
                'SRDI' => '#FF5722',
                'OU5' => '#9C27B0',
                'NLUC' => '#00BCD4',
            ];

            return [
                'label' => $ou,
                'data' => $data->all(),
                'borderColor' => $colors[$ou] ?? '#888',
                'fill' => false,
            ];
        });

        return Inertia::render('app/Dashboard/Superadmin/index', [
            'kpiData' => $kpiData,
            'employeesPerOperatingUnit' => $employeesPerOperatingUnit,
            'employeeStatsPerOu' => $employeeStatsPerOu,
            'operatingUnits' => $operatingUnits,
            'userOperatingUnit' => $userOperatingUnit,
            'hiringTrends' => [
                'labels' => $years,
                'datasets' => $datasets,
            ],
            'degreesCount' => $degreesCount,
        ]);
    }

    private function campusHrDashboard()
    {

        // KPI Data
        $totalActiveEmployees = Employee::where('date_separated', null)->count();
        $totalActiveUsers = User::where('status', 'active')->count();
        $totalTeachinEmployees = Employee::where('date_separated', null)
            ->where('employee_type', 'Teaching')
            ->count();
        $totalNonTeachingEmployees = Employee::where('date_separated', null)
            ->where('employee_type', 'Non-Teaching')
            ->count();

        $kpiData = [
            'totalActiveEmployees' => $totalActiveEmployees,
            'totalActiveUsers' => $totalActiveUsers,
            'totalTeachingEmployees' => $totalTeachinEmployees,
            'totalNonTeachingEmployees' => $totalNonTeachingEmployees,
        ];

        // Employee Status Distribution
        $authUser = Auth::user();
        $operatingUnitOfAuthUser = $authUser->employee ? $authUser->employee->operating_unit_id : null;

        $employeeStatsDistribution = DB::table('employees')
            ->join('operating_units', 'employees.operating_unit_id', '=', 'operating_units.id')
            ->join('job_statuses', 'employees.job_status_id', '=', 'job_statuses.id')
            ->whereNull('employees.date_separated')
            ->when($operatingUnitOfAuthUser, function ($query, $ouId) {
                return $query->where('employees.operating_unit_id', $ouId);
            })
            ->groupBy('job_statuses.name', 'operating_units.shortcut')
            ->select(
                'operating_units.shortcut as operating_unit',
                'job_statuses.name as job_status',
                DB::raw("SUM(CASE WHEN employees.employee_type = 'Teaching' THEN 1 ELSE 0 END) as teaching"),
                DB::raw("SUM(CASE WHEN employees.employee_type = 'Non-Teaching' THEN 1 ELSE 0 END) as non_teaching"),
                DB::raw('COUNT(employees.id) as total')
            )
            ->get();

        // 1️⃣ Get employee hires for last 5 years, only for the user's operating unit
        $fiveYearsAgo = now()->year - 4;

        $employeesHiredPerYearPerOu = DB::table('employees')
            ->join('operating_units', 'employees.operating_unit_id', '=', 'operating_units.id')
            ->whereNull('employees.date_separated') // only active employees
            ->whereNotNull('employees.date_hired')  // ensure date_hired is not null
            ->when($operatingUnitOfAuthUser, fn($q) => $q->where('employees.operating_unit_id', $operatingUnitOfAuthUser))
            ->whereYear('employees.date_hired', '>=', $fiveYearsAgo) // last 5 years
            ->groupBy('operating_units.shortcut', DB::raw('YEAR(employees.date_hired)'))
            ->select(
                'operating_units.shortcut as operating_unit',
                DB::raw('YEAR(employees.date_hired) as year'),
                DB::raw('COUNT(employees.id) as total_hired')
            )
            ->orderBy('year')
            ->get();


        // 2️⃣ Generate years (last 5 years)
        $years = collect(range($fiveYearsAgo, now()->year))->all();

        // 3️⃣ Prepare datasets for the chart
        $operatingUnitShortcut = $employeesHiredPerYearPerOu->first()?->operating_unit ?? null;

        $datasets = [];

        if ($operatingUnitShortcut) {
            $data = collect($years)->map(function ($year) use ($employeesHiredPerYearPerOu, $operatingUnitShortcut) {
                $row = $employeesHiredPerYearPerOu->first(fn($i) => $i->operating_unit === $operatingUnitShortcut && $i->year == $year);
                return $row ? $row->total_hired : 0;
            })->all();

            // Optional: color map
            $colors = [
                'MLUC' => '#26A69A',
            ];

            $datasets[] = [
                'label' => $operatingUnitShortcut,
                'data' => $data,
                'borderColor' => $colors[$operatingUnitShortcut] ?? '#888',
                'fill' => false,
            ];
        }

        // Get Education Attainment Distribution
        // With Masters and Doctorate Degrees

        $degreesCount = DB::table('employees as e')
            ->join('graduate_studies as gs', 'e.id', '=', 'gs.employee_id')
            ->where('gs.highest_level', 'Graduated')
            ->where(function ($q) {
                $q->where('gs.degree_course', 'like', 'Master%')
                    ->orWhere('gs.degree_course', 'like', "Master's%")
                    ->orWhere('gs.degree_course', 'like', 'MS %')
                    ->orWhere('gs.degree_course', 'like', 'Doctor%')
                    ->orWhere('gs.degree_course', 'like', 'Doctorate%');
            })
            ->selectRaw("
        CASE
            WHEN gs.degree_course LIKE 'Master%' OR gs.degree_course LIKE \"Master's%\" THEN 'Master'
            WHEN gs.degree_course LIKE 'Doctor%' OR gs.degree_course LIKE 'Doctorate%' THEN 'Doctorate'
        END AS degree_type,
        COUNT(*) as total
    ")
            ->groupBy('degree_type')
            ->get();


        // dd($degreesCount);




        return Inertia::render('app/Dashboard/HrCampus/index', [
            'kpiData' => $kpiData,
            'employeeStatsDistribution' => $employeeStatsDistribution,
            'hiringTrends' => [
                'labels' => $years,
                'datasets' => $datasets,
            ],
            'degreesCount' => $degreesCount,
        ]);
    }

    private function employeeDashboard()
    {
        return Inertia::render('app/SelfService/Dashboard');
    }
}

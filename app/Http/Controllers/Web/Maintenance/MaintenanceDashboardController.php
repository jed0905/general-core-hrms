<?php

namespace App\Http\Controllers\Web\Maintenance;

use App\Models\Department;
use Inertia\Inertia;
use App\Models\Position;
use App\Models\Designation;
use App\Models\OperatingUnit;
use App\Http\Controllers\Controller;

class MaintenanceDashboardController extends Controller
{
    public function index(){

        // Get the count of all departments
        $totalDepartments = Department::count();

        // Get the count of all designations
        $totalDesignations = Designation::count();

        // Get the count of all operating units
        $totalOperatingUnits = OperatingUnit::count();

        // Get the count of all positions
        $totalPositions = Position::count();

        // Static employment statistics data
        $totalEmployees = 150;
        $employmentData = [
            [
                'status' => 'Permanent',
                'count' => 85,
                'percentage' => 56.7
            ],
            [
                'status' => 'Temporary',
                'count' => 25,
                'percentage' => 16.7
            ],
            [
                'status' => 'Contractual',
                'count' => 20,
                'percentage' => 13.3
            ],
            [
                'status' => 'Casual',
                'count' => 12,
                'percentage' => 8.0
            ],
            [
                'status' => 'Job Order',
                'count' => 8,
                'percentage' => 5.3
            ]
        ];

        // Static monthly employment trends data
        $monthlyTrends = [
            [
                'month' => 'Aug 2024',
                'count' => 5
            ],
            [
                'month' => 'Sep 2024',
                'count' => 8
            ],
            [
                'month' => 'Oct 2024',
                'count' => 12
            ],
            [
                'month' => 'Nov 2024',
                'count' => 6
            ],
            [
                'month' => 'Dec 2024',
                'count' => 9
            ],
            [
                'month' => 'Jan 2025',
                'count' => 15
            ]
        ];

        return Inertia::render('app/Maintenance/Dashboard/Index', [
            'totalDepartments' => $totalDepartments,
            'totalDesignations' => $totalDesignations,
            'totalOperatingUnits' => $totalOperatingUnits,
            'totalPositions' => $totalPositions,
            'totalEmployees' => $totalEmployees,
            'employmentData' => $employmentData,
            'monthlyTrends' => $monthlyTrends,
        ]);
    }
}

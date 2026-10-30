<?php

namespace App\Http\Controllers\Web\Payroll;

use App\Http\Controllers\Controller;
use App\Services\Payroll\PayrollDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayrollDashboardController extends Controller
{
    public function __construct(protected PayrollDashboardService $dashboard) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('app/Payroll/Dashboard/Index', [
            'summary' => $this->dashboard->summary($user),
            'can' => [
                'runs' => $user->can('payroll.view'),
                'setup' => $user->can('payroll.config.view'),
                'rules' => $user->can('payroll.statutory_rule.view'),
                'inputs' => $user->can('payroll.input.manage') || $user->can('payroll.input.approve'),
                'reports' => $user->can('report.payroll'),
            ],
        ]);
    }
}

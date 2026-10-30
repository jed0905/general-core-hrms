<?php

namespace App\Http\Controllers\Web\Payroll;

use App\Http\Controllers\Controller;
use App\Models\PayrollRunEmployee;
use App\Services\Payroll\PayslipService;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Payslips: the employee's own list (finalized runs only) and the payslip
 * page/PDF, both authorized by PayslipPolicy.
 */
class PayslipController extends Controller
{
    public function __construct(protected PayslipService $payslips) {}

    public function mine(Request $request): Response
    {
        $this->authorize('viewOwn', PayrollRunEmployee::class);

        return Inertia::render('app/Payroll/MyPayslips/Index', [
            'payslips' => $this->payslips->getMyPayslips($request->user()),
        ]);
    }

    public function show(PayrollRunEmployee $payslip): Response
    {
        $this->authorize('view', $payslip);

        return Inertia::render('app/Payroll/Payslips/Show', [
            'payslip' => $this->payslips->document($payslip),
        ]);
    }

    public function pdf(PayrollRunEmployee $payslip): HttpResponse
    {
        $this->authorize('view', $payslip);

        return $this->payslips->pdf($payslip);
    }
}

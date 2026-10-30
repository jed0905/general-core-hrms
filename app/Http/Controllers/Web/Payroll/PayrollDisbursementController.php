<?php

namespace App\Http\Controllers\Web\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\DisbursementStatusRequest;
use App\Http\Requests\Payroll\Pain001ExportRequest;
use App\Models\PayrollDisbursement;
use App\Models\PayrollRun;
use App\Services\Payroll\DisbursementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Payment tracking and payment files for finalized runs. No bank is contacted.
 */
class PayrollDisbursementController extends Controller
{
    public function __construct(protected DisbursementService $disbursements) {}

    public function update(DisbursementStatusRequest $request, PayrollRun $payrollRun, PayrollDisbursement $payrollDisbursement): RedirectResponse
    {
        abort_unless($payrollDisbursement->payroll_run_id === $payrollRun->id, 404);
        $this->disbursements->updateStatus($payrollDisbursement, $request->validated(), $request->user());

        return back()->with('success', 'Payment status updated.');
    }

    public function csv(Request $request, PayrollRun $payrollRun): StreamedResponse
    {
        $this->authorize('disburse', $payrollRun);

        return $this->disbursements->exportCsv($payrollRun, $request->user());
    }

    public function pain001(Pain001ExportRequest $request, PayrollRun $payrollRun): StreamedResponse
    {
        return $this->disbursements->exportPain001($payrollRun, $request->validated(), $request->user());
    }
}

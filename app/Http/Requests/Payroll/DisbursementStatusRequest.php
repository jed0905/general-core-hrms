<?php

namespace App\Http\Requests\Payroll;

use App\Models\PayrollDisbursement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Record a payment outcome for one disbursement.
 */
class DisbursementStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('disburse', $this->route('payrollRun'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(PayrollDisbursement::STATUSES)],
            'method' => ['nullable', Rule::in(PayrollDisbursement::METHODS)],
            'reference' => ['nullable', 'string', 'max:100'],
            'failure_reason' => ['nullable', 'required_if:status,failed', 'string', 'max:255'],
        ];
    }
}

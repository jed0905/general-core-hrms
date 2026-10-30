<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Employer (debtor) account for an ISO 20022 pain.001 file; used for the file only, never stored.
 */
class Pain001ExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('disburse', $this->route('payrollRun'));
    }

    public function rules(): array
    {
        return [
            'iban' => ['nullable', 'required_without:account_number', 'string', 'max:34'],
            'account_number' => ['nullable', 'required_without:iban', 'string', 'max:64'],
            'bic' => ['nullable', 'string', 'max:11'],
        ];
    }
}

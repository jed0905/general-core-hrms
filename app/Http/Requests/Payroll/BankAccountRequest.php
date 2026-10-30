<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A salary payment account. Numbers are encrypted at rest.
 */
class BankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.config.manage');
    }

    public function rules(): array
    {
        return [
            'bank_name' => ['required', 'string', 'max:255'],
            'bank_identifier' => ['nullable', 'string', 'max:32'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9 -]+$/'],
            'iban' => ['nullable', 'string', 'max:34'],
            'currency' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'is_primary' => ['boolean'],
        ];
    }
}

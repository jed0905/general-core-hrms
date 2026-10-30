<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

/**
 * CSV of employee_number,category,quantity rows.
 */
class ImportTimeInputsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.input.manage');
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }
}

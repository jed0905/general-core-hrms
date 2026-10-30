<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A lifecycle action on a run; the controller authorizes the specific ability. Returning, cancelling and reversing require remarks.
 */
class PayrollRunActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('payrollRun'));
    }

    public function rules(): array
    {
        return [
            'remarks' => [$this->routeIs('payroll.runs.return', 'payroll.runs.cancel', 'payroll.runs.reverse') ? 'required' : 'nullable', 'string', 'max:1000'],
        ];
    }
}

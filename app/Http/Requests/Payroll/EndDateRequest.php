<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

/**
 * End an effective-dated record on a date.
 */
class EndDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.config.manage') || $this->user()->can('compensation.update');
    }

    public function rules(): array
    {
        return [
            'effective_to' => ['required', 'date_format:Y-m-d'],
        ];
    }
}

<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Change a period's cut-off or pay date (only while it has no runs).
 */
class UpdatePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.config.manage');
    }

    public function rules(): array
    {
        return [
            'cutoff_date' => ['nullable', 'date_format:Y-m-d'],
            'pay_date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}

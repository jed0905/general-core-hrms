<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Build attendance summaries for a payroll period.
 */
class GenerateSummariesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('attendance.process');
    }

    public function rules(): array
    {
        return [
            'payroll_period_id' => ['required', 'exists:payroll_periods,id'],
        ];
    }
}

<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Approve attendance summaries so payroll can use them.
 */
class ApproveSummariesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('attendance.finalize');
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ];
    }
}

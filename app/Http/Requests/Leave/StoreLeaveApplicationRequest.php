<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'reason' => ['nullable', 'string', 'max:500'],
            'dates' => ['required', 'array', 'min:1'],
            'dates.*.leave_date' => ['required', 'date'],
            'dates.*.duration_type' => ['required', 'in:full_day,half_day,hours'],
            'dates.*.hours' => ['required_if:dates.*.duration_type,hours', 'nullable', 'numeric', 'gt:0', 'lte:8'],
            'dates.*.start_time' => ['nullable', 'date_format:H:i'],
            'dates.*.end_time' => ['nullable', 'date_format:H:i'],
            'dates.*.is_paid' => ['nullable', 'boolean'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
        ];
    }
}

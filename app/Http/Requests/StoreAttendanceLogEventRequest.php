<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceLogEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'employee_id' => ['nullable', 'exists:employees,id'],
            'date' => ['required', 'date'],
            'att_log_event_type_id' => ['required', 'exists:attendance_log_event_types,id'],
            'coverage' => ['required', 'string', 'in:whole_day,am,pm,custom'],
            'start_time' => ['required_if:coverage,custom', 'nullable', 'date_format:H:i'],
            'end_time' => ['required_if:coverage,custom', 'nullable', 'date_format:H:i', 'after:start_time'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'], // 10MB
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'The date field is required.',
            'date.date' => 'The date must be a valid date.',

            'att_log_event_type_id.required' => 'Please select an attendance log event type.',
            'att_log_event_type_id.exists' => 'The selected attendance log event type is invalid.',

            'remarks.string' => 'Remarks must be a valid text.',
            'remarks.max' => 'Remarks may not exceed 1000 characters.',

            'documents.array' => 'Documents must be uploaded as a valid file list.',
            'documents.*.file' => 'Each uploaded document must be a valid file.',
            'documents.*.mimes' => 'Documents must be a file of type: pdf, jpg, jpeg, png.',
            'documents.*.max' => 'Each document must not exceed 10MB.',
        ];
    }
}

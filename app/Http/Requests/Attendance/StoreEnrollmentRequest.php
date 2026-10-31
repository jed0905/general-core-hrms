<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Map an employee to a device-specific user ID.
 */
class StoreEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('attendance.enrollment.manage');
    }

    public function rules(): array
    {
        return [
            'attendance_device_id' => ['required', 'exists:attendance_devices,id'],
            'employee_id' => ['required', 'exists:employees,id'],
            'device_user_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'card_number' => ['nullable', 'string', 'max:64'],
            'external_reference' => ['nullable', 'string', 'max:128'],
            'status' => ['nullable', Rule::in(['pending', 'active'])],
            'enrolled_on' => ['nullable', 'date_format:Y-m-d'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ];
    }
}

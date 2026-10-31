<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The employee's device user ID changed.
 */
class ReassignEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('attendance.enrollment.manage');
    }

    public function rules(): array
    {
        return [
            'device_user_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ];
    }
}

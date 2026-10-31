<?php

namespace App\Http\Requests\Attendance;

use App\Models\AttendanceEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A manually entered punch with a reason.
 */
class StoreManualEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('attendance.create');
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'occurred_at' => ['required', 'date_format:Y-m-d H:i'],
            'event_type' => ['required', Rule::in(AttendanceEvent::TYPES)],
            'attendance_device_id' => ['nullable', 'exists:attendance_devices,id'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}

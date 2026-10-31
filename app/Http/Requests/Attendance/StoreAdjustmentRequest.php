<?php

namespace App\Http\Requests\Attendance;

use App\Models\AttendanceAdjustment;
use App\Models\AttendanceEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request an attendance correction.
 */
class StoreAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('attendance.adjust');
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'work_date' => ['required', 'date_format:Y-m-d'],
            'type' => ['required', Rule::in(AttendanceAdjustment::TYPES)],
            'attendance_event_id' => ['nullable', 'integer'],
            'occurred_at' => ['nullable', 'date_format:Y-m-d H:i'],
            'event_type' => ['nullable', Rule::in(AttendanceEvent::TYPES)],
            'minutes' => ['nullable', 'integer', 'between:1,1440'],
            'reason' => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

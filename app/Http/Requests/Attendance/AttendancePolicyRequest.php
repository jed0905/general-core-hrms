<?php

namespace App\Http\Requests\Attendance;

use App\Models\AttendancePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Company attendance rules (no statutory values).
 */
class AttendancePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('attendance.config.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'legal_entity_id' => ['nullable', 'exists:legal_entities,id', Rule::unique('attendance_policies', 'legal_entity_id')->ignore($this->route('attendancePolicy'))],
            'late_grace_minutes' => ['required', 'integer', 'between:0,240'],
            'undertime_grace_minutes' => ['required', 'integer', 'between:0,240'],
            'pairing_mode' => ['required', Rule::in(AttendancePolicy::PAIRING_MODES)],
            'debounce_minutes' => ['required', 'integer', 'between:0,60'],
            'deduct_scheduled_break' => ['boolean'],
            'overtime_threshold_minutes' => ['required', 'integer', 'between:0,480'],
            'overtime_requires_approval' => ['boolean'],
            'count_early_arrival_as_overtime' => ['boolean'],
            'night_start' => ['nullable', 'date_format:H:i', 'required_with:night_end'],
            'night_end' => ['nullable', 'date_format:H:i', 'required_with:night_start'],
            'window_before_minutes' => ['required', 'integer', 'between:0,720'],
            'day_start_time' => ['required', 'date_format:H:i'],
            'timezone' => ['nullable', 'timezone'],
            'is_active' => ['boolean'],
        ];
    }
}

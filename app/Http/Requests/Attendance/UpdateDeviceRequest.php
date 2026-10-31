<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edit device details (not vendor, not connection or credentials).
 */
class UpdateDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('attendance.device.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:64'],
            'serial_number' => ['nullable', 'string', 'max:64', Rule::unique('attendance_devices', 'serial_number')->where('vendor', $this->route('attendanceDevice')->vendor)->ignore($this->route('attendanceDevice'))],
            'legal_entity_id' => ['nullable', 'exists:legal_entities,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'timezone' => ['required', 'timezone'],
        ];
    }
}

<?php

namespace App\Http\Requests\Attendance;

use App\Models\AttendanceDevice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Register a device. Credentials are optional here and require attendance.device.configure.
 */
class StoreDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('attendance.device.manage') && (! $this->filled('password') || $this->user()->can('attendance.device.configure'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'vendor' => ['required', Rule::in(AttendanceDevice::VENDORS)],
            'model' => ['nullable', 'string', 'max:64'],
            'serial_number' => ['nullable', 'string', 'max:64', Rule::unique('attendance_devices', 'serial_number')->where('vendor', $this->input('vendor'))],
            'legal_entity_id' => ['nullable', 'exists:legal_entities,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'timezone' => ['required', 'timezone'],
            'connection_method' => ['required', 'string', 'max:24'],
            'host' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9.\-:\[\]]+$/'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'use_tls' => ['boolean'],
            'verify_tls' => ['boolean'],
            'attendance_connector_id' => ['nullable', 'exists:attendance_connectors,id'],
            'page_size' => ['nullable', 'integer', 'between:1,100'],
            'username' => ['nullable', 'string', 'max:64'],
            'password' => ['nullable', 'string', 'max:128'],
            'settings' => ['nullable', 'array'],
            'settings.acs_event_minors' => ['nullable', 'array'],
            'settings.acs_event_minors.*' => ['integer', 'between:0,1000'],
            'simulation' => ['nullable', 'array'],
        ];
    }
}

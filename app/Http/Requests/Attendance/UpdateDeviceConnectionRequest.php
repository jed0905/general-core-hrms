<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Connection method, address and credentials (blank password keeps the stored one).
 */
class UpdateDeviceConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('attendance.device.configure');
    }

    public function rules(): array
    {
        return [
            'connection_method' => ['required', 'string', 'max:24'],
            'host' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9.\-:\[\]]+$/'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'use_tls' => ['boolean'],
            'verify_tls' => ['boolean'],
            'attendance_connector_id' => ['nullable', 'exists:attendance_connectors,id'],
            'page_size' => ['nullable', 'integer', 'between:1,100'],
            'username' => ['nullable', 'string', 'max:64'],
            'password' => ['nullable', 'string', 'max:128'],
            'clear_credentials' => ['boolean'],
            'settings' => ['nullable', 'array'],
            'settings.acs_event_minors' => ['nullable', 'array'],
            'settings.acs_event_minors.*' => ['integer', 'between:0,1000'],
            'simulation' => ['nullable', 'array'],
        ];
    }
}

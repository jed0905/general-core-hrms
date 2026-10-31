<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

/**
 * CSV of employee_number,date_time,type,device_serial rows.
 */
class ImportEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('attendance.log.import');
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
        ];
    }
}

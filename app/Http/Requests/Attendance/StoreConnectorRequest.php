<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Register an on-premises connector (its secret is shown once).
 */
class StoreConnectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('attendance.device.configure');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'legal_entity_id' => ['nullable', 'exists:legal_entities,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
        ];
    }
}

<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Approve or reject an adjustment (rejection needs remarks).
 */
class DecideAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('attendance.approve_adjustment');
    }

    public function rules(): array
    {
        return [
            'remarks' => [$this->routeIs('*.reject') ? 'required' : 'nullable', 'string', 'max:255'],
        ];
    }
}

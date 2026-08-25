<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePositionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'operating_unit_id' => 'required|exists:operating_units,id',
            'government_positions_id' => 'required|exists:government_positions,id',
            'plantilla_item_number' => 'nullable|string|max:255',
            'salary_grade_id' => 'required|exists:salary_grades,id',
        ];
    }

    public function messages(): array
    {
        return [
            'operating_unit_id.exists' => 'Operating unit not found.',
            'government_positions_id.exists' => 'Government position not found.',
            'salary_grade_id.exists' => 'Salary grade not found.',
        ];
    }
}

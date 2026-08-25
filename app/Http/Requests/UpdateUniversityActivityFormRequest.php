<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUniversityActivityFormRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'start_at' => 'required|date',
            'end_at' => 'required|date',
            'operating_unit_ids' => 'nullable|array',
            'operating_unit_ids.*' => 'integer|exists:operating_units,id',
            'document_control_number' => 'nullable|string|min:5',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Title is required.',
            'title.string' => 'Title must be a string.',
            'title.max' => 'Title must be less than 255 characters.',
            'type.required' => 'Type is required.',
            'type.string' => 'Type must be a string.',
            'type.max' => 'Type must be less than 255 characters.',
            'description.nullable' => 'Description is required.',
            'description.string' => 'Description must be a string.',
            'description.max' => 'Description must be less than 255 characters.',
            'start_at.required' => 'Start date is required.',
            'start_at.date' => 'Start date must be a date.',
            'end_at.required' => 'End date is required.',
            'end_at.date' => 'End date must be a date.',
            'operating_unit_ids.nullable' => 'Operating unit is required.',
            'operating_unit_ids.array' => 'Operating unit must be an array.',
            'operating_unit_ids.*.integer' => 'Operating unit must be an integer.',
            'operating_unit_ids.*.exists' => 'Operating unit must exist.',
            'document_control_number.min' => 'Document control number must be at least 5 characters.',

        ];
    }
}

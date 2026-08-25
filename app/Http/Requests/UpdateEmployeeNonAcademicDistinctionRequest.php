<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeNonAcademicDistinctionRequest extends FormRequest
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
            'nonAcademicDistinctions' => 'nullable|array',
            'nonAcademicDistinctions.*.id' => 'nullable|integer|exists:other_info_non_academic_distinctions,id',
            'nonAcademicDistinctions.*.distinction' => 'nullable|string',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeLearningAndDevelopmentFormRequest extends FormRequest
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
            'learningDevelopment' => [
                'employee_id' => ['required', 'integer', 'exists:employees,id'],
                'title' => ['required', 'string', 'max:255'],
                'from' => ['required', 'date'],
                'to' => ['nullable', 'date', 'after_or_equal:from'],
                'training_hours' => ['required', 'integer', 'min:1'],
                'type_of_ld' => ['required', 'string', 'max:255'],
                'conducted_by' => ['required', 'string', 'max:255'],
            ]
        ];
    }
}

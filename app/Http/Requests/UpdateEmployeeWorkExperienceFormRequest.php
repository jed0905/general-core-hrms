<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeWorkExperienceFormRequest extends FormRequest
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
            'workExperiences' => [
                'from' => ['required', 'string'],
                'to' => ['required', 'string'],
                'position_title' => ['required', 'string'],
                'department_agency' => ['required', 'string'],
                'monthly_salary' => ['required', 'string'],
                'salary_grade' => ['required', 'string'],
                'status_of_appointment' => ['required', 'string'],
                'government_service' => ['required', 'string'],
            ],
        ];
    }
}

<?php

namespace App\Http\Requests\Careers;

use Illuminate\Foundation\Http\FormRequest;

/**
 * What a candidate may change themselves. Names and email go through HR.
 */
class PublicApplicantProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $edu = 'education.*.';
        $work = 'work_experience.*.';

        return [
            'preferred_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^\+?[0-9(][0-9\s\-().]{6,24}$/'],
            'alternate_phone' => ['nullable', 'string', 'max:40', 'regex:/^\+?[0-9(][0-9\s\-().]{6,24}$/'],
            'address' => ['nullable', 'string', 'max:1000'],
            'education' => ['nullable', 'array', 'max:20'],
            $edu.'level' => ['nullable', 'string', 'max:100'],
            $edu.'institute' => ['required', 'string', 'max:191'],
            $edu.'degree' => ['nullable', 'string', 'max:191'],
            $edu.'major_specialization' => ['nullable', 'string', 'max:191'],
            $edu.'start_date' => ['nullable', 'date'],
            $edu.'end_date' => ['nullable', 'date', "after_or_equal:{$edu}start_date"],
            $edu.'is_completed' => ['nullable', 'boolean'],
            'work_experience' => ['nullable', 'array', 'max:30'],
            $work.'company' => ['required', 'string', 'max:191'],
            $work.'job_title' => ['required', 'string', 'max:191'],
            $work.'from' => ['required', 'date'],
            $work.'to' => ['nullable', 'date', "after_or_equal:{$work}from"],
            $work.'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

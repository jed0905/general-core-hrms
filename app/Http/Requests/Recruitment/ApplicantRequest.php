<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Applicant profile rules. Only what recruitment needs is collected
 * (no date of birth, civil status, etc.). Email or phone is required so
 * the applicant can be contacted and de-duplicated.
 */
abstract class ApplicantRequest extends FormRequest
{
    protected function ignoreId(): ?int
    {
        return null;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:50'],
            'preferred_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:191'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:50', 'regex:/^\+?[0-9(][0-9\s\-().]{6,24}$/'],
            'alternate_phone' => ['nullable', 'string', 'max:50', 'regex:/^\+?[0-9(][0-9\s\-().]{6,24}$/'],
            'address' => ['nullable', 'string', 'max:1000'],
            'recruitment_source_id' => ['nullable', 'integer', Rule::exists('recruitment_sources', 'id')->where('is_active', true)],
            'source_details' => ['nullable', 'string', 'max:255'],
            'is_internal' => ['boolean'],
            'employee_id' => ['nullable', 'required_if:is_internal,true,1', 'integer', 'exists:employees,id', Rule::unique('applicants', 'employee_id')->ignore($this->ignoreId())],
            // HR confirms this is a different person after seeing a duplicate warning.
            'confirm_not_duplicate' => ['boolean'],

            'education' => ['nullable', 'array', 'max:20'],
            'education.*.level' => ['nullable', 'string', 'max:255'],
            'education.*.institute' => ['nullable', 'string', 'max:255', 'required_with:education.*.degree,education.*.major_specialization,education.*.start_date,education.*.end_date'],
            'education.*.degree' => ['nullable', 'string', 'max:255'],
            'education.*.major_specialization' => ['nullable', 'string', 'max:255'],
            'education.*.start_date' => ['nullable', 'date'],
            'education.*.end_date' => ['nullable', 'date', 'after_or_equal:education.*.start_date'],
            'education.*.is_completed' => ['boolean'],
            'education.*.notes' => ['nullable', 'string', 'max:1000'],

            // Same canonical fields as employee work experience: from / to / notes ("to" empty = current role).
            'work_experience' => ['nullable', 'array', 'max:30'],
            'work_experience.*.company' => ['nullable', 'string', 'max:255', 'required_with:work_experience.*.job_title,work_experience.*.from,work_experience.*.to,work_experience.*.notes'],
            'work_experience.*.job_title' => ['nullable', 'string', 'max:255', 'required_with:work_experience.*.company,work_experience.*.from,work_experience.*.to,work_experience.*.notes'],
            'work_experience.*.from' => ['nullable', 'date', 'required_with:work_experience.*.company,work_experience.*.job_title'],
            'work_experience.*.to' => ['nullable', 'date', 'after_or_equal:work_experience.*.from'],
            'work_experience.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required_without' => 'Enter an email address or a phone number.',
            'phone.required_without' => 'Enter a phone number or an email address.',
            'phone.regex' => 'Enter a valid phone number.',
            'alternate_phone.regex' => 'Enter a valid phone number.',
            'employee_id.required_if' => 'Select the employee for an internal candidate.',
            'employee_id.unique' => 'This employee already has an applicant record.',
        ];
    }

    public function attributes(): array
    {
        return [
            'recruitment_source_id' => 'source',
            'education.*.institute' => 'school / institution',
            'work_experience.*.from' => 'start date',
            'work_experience.*.to' => 'end date',
            'work_experience.*.notes' => 'description',
        ];
    }
}

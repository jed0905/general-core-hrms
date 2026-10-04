<?php

namespace App\Http\Requests\Recruitment;

use App\Models\Applicant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Only what recruitment doesn't already know: the employee's sex (required by
 * Core HR, never collected from applicants), an optional supervisor, which
 * submitted documents to copy, and an optional login (same rules as User
 * Management). Everything else comes from the applicant and the accepted offer.
 * Eligibility is checked in ApplicationConversionService against locked rows.
 */
class ConvertApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('convert', $this->route('application'));
    }

    public function rules(): array
    {
        $applicant = Applicant::find($this->route('application')->applicant_id);
        $newEmployee = ! ($applicant?->employee_id || $applicant?->converted_employee_id);

        return [
            'emp_sex' => [Rule::requiredIf($newEmployee), 'nullable', Rule::in(['male', 'female', 'other'])],
            'supervisor_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->whereNotIn('status', ['archived', 'terminated'])],
            'document_ids' => ['nullable', 'array'],
            'document_ids.*' => ['integer', 'distinct'],
            'create_user_account' => ['sometimes', 'boolean', Rule::prohibitedIf(! $newEmployee && $this->boolean('create_user_account'))],
            'username' => ['nullable', 'required_if_accepted:create_user_account', 'string', 'max:255', Rule::unique('users', 'username')],
            'password' => ['nullable', 'required_if_accepted:create_user_account', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'emp_sex.required' => 'Core HR requires the employee\'s sex; it isn\'t collected from applicants.',
            'create_user_account.prohibited' => 'This candidate is already an employee; manage their login in User Management.',
        ];
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);

        if ($key === null && empty($data['create_user_account'])) {
            unset($data['username'], $data['password']);
        }

        return $data;
    }
}

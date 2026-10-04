<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Edit a scheduled interview's type, mode, place, instructions and panel.
 */
class UpdateInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('interview'));
    }

    public function rules(): array
    {
        return ScheduleInterviewRequest::detailRules();
    }

    public function messages(): array
    {
        return (new ScheduleInterviewRequest)->messages();
    }
}

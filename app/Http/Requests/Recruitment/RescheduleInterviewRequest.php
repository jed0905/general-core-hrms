<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('interview'));
    }

    public function rules(): array
    {
        return ScheduleInterviewRequest::timeRules() + [
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

<?php

namespace App\Http\Requests\Recruitment;

use App\Models\ApplicationInterview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Schedule an interview (or, with UpdateInterviewRequest, edit one). Stage,
 * application state and double booking are checked in InterviewService.
 */
class ScheduleInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('scheduleInterview', $this->route('application'));
    }

    public function rules(): array
    {
        return self::detailRules() + self::timeRules();
    }

    public static function detailRules(): array
    {
        return [
            'interview_type_id' => ['required', 'integer', Rule::exists('interview_types', 'id')->where('is_active', true)],
            'mode' => ['required', Rule::in(ApplicationInterview::MODES)],
            'location' => ['nullable', 'required_if:mode,'.ApplicationInterview::MODE_IN_PERSON, 'string', 'max:255'],
            'meeting_url' => ['nullable', 'required_if:mode,'.ApplicationInterview::MODE_VIDEO, 'url:http,https', 'max:500'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'panelist_ids' => ['required', 'array', 'min:1', 'max:10'],
            'panelist_ids.*' => ['required', 'integer', 'distinct', Rule::exists('employees', 'id')->whereNotIn('status', ['archived', 'terminated'])],
            'primary_panelist_id' => ['nullable', 'integer', 'in_array:panelist_ids.*'],
        ];
    }

    public static function timeRules(): array
    {
        return [
            'scheduled_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
        ];
    }

    public function messages(): array
    {
        return [
            'location.required_if' => 'Enter where the in-person interview takes place.',
            'meeting_url.required_if' => 'Enter the meeting link for a video interview.',
            'panelist_ids.required' => 'Assign at least one panelist.',
            'panelist_ids.*.exists' => 'Panelists must be current employees.',
        ];
    }
}

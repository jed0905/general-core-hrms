<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Date range for one employee's attendance history (HR view or self-service).
 * Authorization happens on the employee record in the controller.
 */
class AttendanceHistoryRequest extends FormRequest
{
    public const MAX_DAYS = 93;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from', function ($attribute, $value, $fail) {
                $from = $this->input('date_from');
                if ($from && (strtotime($value) - strtotime((string) $from)) / 86400 + 1 > self::MAX_DAYS) {
                    $fail('Choose a range of at most '.self::MAX_DAYS.' days.');
                }
            }],
        ];
    }
}

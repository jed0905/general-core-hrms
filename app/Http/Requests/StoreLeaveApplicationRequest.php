<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveApplicationRequest extends FormRequest
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
            'employeeId' => ['required', 'exists:employees,id'],
            'from' => ['required', 'date', 'before_or_equal:to'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'leaveType' => ['required',
                Rule::in([
                    'Vacation Leave',
                    'Mandatory/Forced Leave',
                    'Sick Leave',
                    'Maternity Leave',
                    'Paternity Leave',
                    'Special Privilege Leave',
                    'Solo Parent Leave',
                    'Study Leave',
                    'Rehabilitation Leave',
                    'Special Leave Benefits for Women',
                    'Special Leave for Women',
                    'Special Emergency (Calamity) Leave',
                    'Adoption Leave',
                    'Others',
                ]),
            ],
            'workingDays' => ['required', 'numeric'],

            'location' => [
                Rule::requiredIf(function () {
                    return in_array($this->leaveType, [
                        'Vacation Leave',
                        'Special Privilege Leave',
                        'Mandatory/Forced Leave',
                        'Special Emergency (Calamity) Leave',
                        'Rehabilitation Leave',
                        'Solo Parent Leave',
                        'Adoption Leave',
                        'Maternity Leave',
                        'Paternity Leave',
                        'Special Leave for Women (RA 19710)',
                        'Special Leave for Women',
                    ]);
                }),
                'nullable', 'string', 'max:255',
            ],

            'illness' => [
                Rule::requiredIf(fn () => $this->leaveType === 'Sick Leave'),
                'nullable', 'string', 'max:255'
            ],

            // Study Leave details: require when leaveType is Study Leave
            'study_leave_application' => [
                Rule::requiredIf(fn () => $this->leaveType === 'Study Leave'),
                'nullable', 'string', 'max:255'
            ],

            'locationType' => ['nullable', 'string', 'in:within,abroad'],
            'sickLeaveType' => ['nullable', 'string', 'in:hospital,outPatient'],
            'otherPurpose' => ['nullable', 'string', 'max:255'],
            'commutation' => ['nullable', 'string'],
            'location_within_philippines' => ['nullable', 'string', 'max:255'],
            'location_abroad' => ['nullable', 'string', 'max:255'],
            'in_hospital' => ['nullable', 'string', 'in:true,false'],
            'out_hospital' => ['nullable', 'string', 'in:true,false'],
            'leave_duration' => ['nullable', 'string', 'in:Full Day,Half Day - Morning,Half Day - Afternoon'],
            'partial_days' => ['nullable', 'string', 'in:All Days,Start Date Only,End Date Only,Start and End Date'],
            'start_day' => ['nullable', 'string', 'in:Half Day - Morning,Half Day - Afternoon'],
            'end_day' => ['nullable', 'string', 'in:Half Day - Morning,Half Day - Afternoon'],
            'specialLeaveCreditId' => ['nullable', 'exists:employee_leave_credits_histories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'from.required' => 'The start date is required.',
            'to.required' => 'The end date is required.',
            'to.after_or_equal' => 'The end date cannot be before the start date.',
            'leaveType.required' => 'Please select a leave type.',
            'workingDays.required' => 'The number of working days must be calculated.',
            'location.required' => 'Please specify a location for this leave type.',
            'illness.required' => 'Please specify the illness for Sick Leave.',
            'study_leave_application.required' => 'Please select a study leave option.',
            'leave_duration.required' => 'Please select a leave duration.',
            'partial_days.required' => 'Please select a partial days.',
            'start_day.required' => 'Please select a start day.',
            'end_day.required' => 'Please select an end day.',
        ];
    }
}

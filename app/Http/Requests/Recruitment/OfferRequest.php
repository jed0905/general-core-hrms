<?php

namespace App\Http\Requests\Recruitment;

use App\Models\JobOffer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Offer terms (create a draft for an application, or edit a draft). Whether
 * the candidate is selected, the vacancy live and the offer still a draft is
 * checked in OfferService.
 */
class OfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $offer = $this->route('offer');

        return $offer
            ? $this->user()->can('update', $offer)
            : $this->user()->can('create', [JobOffer::class, $this->route('application')]);
    }

    public function rules(): array
    {
        return [
            'job_title_id' => ['required', 'integer', Rule::exists('job_titles', 'id')],
            'employment_status_id' => ['nullable', 'integer', Rule::exists('employment_statuses', 'id')],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
            'work_location' => ['nullable', 'string', 'max:255'],
            'proposed_start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:expiry_date'],
            'expiry_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'base_salary' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'salary_frequency' => ['required', Rule::in(JobOffer::FREQUENCIES)],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'benefits' => ['nullable', 'string', 'max:5000'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return ['proposed_start_date.after_or_equal' => 'The proposed start date must be on or after the offer\'s expiry date.'];
    }
}

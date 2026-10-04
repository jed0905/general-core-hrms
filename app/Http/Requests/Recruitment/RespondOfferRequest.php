<?php

namespace App\Http\Requests\Recruitment;

use App\Models\JobOffer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * HR records the candidate's answer to an issued offer.
 */
class RespondOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('respond', $this->route('offer'));
    }

    public function rules(): array
    {
        return [
            'response' => ['required', Rule::in([JobOffer::RESPONSE_ACCEPTED, JobOffer::RESPONSE_DECLINED])],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

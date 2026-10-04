<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

/**
 * An approver rejects the offer (not the candidate's rejection; see RespondOfferRequest).
 */
class RejectOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reject', $this->route('offer'));
    }

    public function rules(): array
    {
        return ['remarks' => ['required', 'string', 'max:2000']];
    }
}

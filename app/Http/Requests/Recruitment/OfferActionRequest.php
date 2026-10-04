<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Submit for approval, approve or issue an offer: optional remarks. The
 * ability matches the route (submit → update).
 */
class OfferActionRequest extends FormRequest
{
    private const ABILITIES = [
        'recruitment.offers.submit' => 'update',
        'recruitment.offers.approve' => 'approve',
        'recruitment.offers.issue' => 'issue',
    ];

    public function authorize(): bool
    {
        $ability = self::ABILITIES[$this->route()->getName()] ?? null;

        return $ability !== null && $this->user()->can($ability, $this->route('offer'));
    }

    public function rules(): array
    {
        return ['remarks' => ['nullable', 'string', 'max:2000']];
    }
}

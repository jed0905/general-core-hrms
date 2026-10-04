<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('withdraw', $this->route('offer'));
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:2000']];
    }
}

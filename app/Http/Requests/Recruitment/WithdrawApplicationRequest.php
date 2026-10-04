<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('withdraw', $this->route('application'));
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:1000']];
    }
}

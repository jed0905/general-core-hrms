<?php

namespace App\Http\Requests\Careers;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawPublicApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:1000']];
    }
}

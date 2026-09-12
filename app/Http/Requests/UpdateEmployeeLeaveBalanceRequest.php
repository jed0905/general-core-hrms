<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeLeaveBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'balance' => ['required', 'numeric', 'min:0'],
            'used' => ['required', 'numeric', 'min:0'],
            'pending' => ['required', 'numeric', 'min:0'],
            'as_of_date' => ['required', 'date'],
        ];
    }
}

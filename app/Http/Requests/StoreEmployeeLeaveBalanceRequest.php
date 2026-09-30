<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeLeaveBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('leave.balance.create');
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'balance' => ['required', 'numeric', 'min:0'],
            'used' => ['nullable', 'numeric', 'min:0'],
            'pending' => ['nullable', 'numeric', 'min:0'],
            'as_of_date' => ['required', 'date'],
        ];
    }
}

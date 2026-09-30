<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustEmployeeLeaveBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('leave.balance.adjust');
    }

    public function rules(): array
    {
        return [
            'leave_balance_id' => ['required', 'exists:employee_leave_balances,id'],
            'type' => ['required', 'in:add,deduct'],
            'days' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}

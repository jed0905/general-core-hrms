<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeLeaveEntitlementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {

        return [
            'allOrNot' => 'required',
            'employeeId' => 'required_if:addTo,individual',
            'operatingUnitId' => 'required_if:addTo,multiple',
            'leaveTypeId' => 'required',
            'specialLeaveId' => 'required_if:addTo,multiple',
            'documentControlNumber' => 'required_if:addTo,multiple',
            'expirationDateFrom' => 'required_if:addTo,multiple',
            'expirationDateTo' => 'required_if:addTo,multiple',
            'entitlement' => 'required',
        ];
    }
}

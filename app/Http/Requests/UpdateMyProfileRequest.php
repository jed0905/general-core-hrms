<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Self-service contact update. The target is always the user's own employee
 * record; any employee_id or HR-managed field in the payload is ignored
 * because only these rules are validated and written.
 */
class UpdateMyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employee = $this->ownEmployee();

        return $employee !== null && $this->user()->can('updateContactDetails', $employee);
    }

    public function rules(): array
    {
        return [
            'street1' => ['nullable', 'string', 'max:255'],
            'street2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'country_id' => ['nullable', 'string', 'max:255'],
            'home_telephone_no' => ['nullable', 'string', 'max:50'],
            'mobile_no' => ['nullable', 'string', 'max:50'],
            'other_email' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function ownEmployee(): ?Employee
    {
        $employeeId = $this->user()?->employee_id;

        return $employeeId ? Employee::find($employeeId) : null;
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeSpouseInformationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,

            'spouse_lastname' => $this->spouse_lastname,
            'spouse_firstname' => $this->spouse_firstname,
            'spouse_middlename' => $this->spouse_middlename,
            'spouse_suffix' => $this->spouse_suffix,
            'occupation' => $this->occupation,
            'employer_business_name' => $this->employer_business_name,
            'business_address' => $this->business_address,
            'telephone_no' => $this->telephone_no,
        ];
    }
}

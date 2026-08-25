<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeFamilyBackgroundResource extends JsonResource
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

            'father_lastname' => $this->father_lastname,
            'father_firstname' => $this->father_firstname,
            'father_middlename' => $this->father_middlename,
            'mother_lastname' => $this->mother_lastname,
            'mother_firstname' => $this->mother_firstname,
            'mother_middlename' => $this->mother_middlename,

            'children' => $this->children,
        ];
    }
}

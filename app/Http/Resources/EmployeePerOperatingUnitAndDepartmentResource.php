<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeePerOperatingUnitAndDepartmentResource extends JsonResource
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
            'personal_information' => [
                'firstname' => $this->personalInformation->firstname ?? null,
                'middlename' => $this->personalInformation->middlename ?? null,
                'lastname' => $this->personalInformation->lastname ?? null,
                'suffix' => $this->personalInformation->suffix ?? null,
            ],
            'job_status' => [
                'id' => $this->jobStatus->id ?? null,
                'name' => $this->jobStatus->name ?? null,
            ],
            'department' => [
                'id' => $this->department->id ?? null,
                'name' => $this->department->name ?? null,
            ],
        ];
    }
}

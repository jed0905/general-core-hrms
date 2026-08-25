<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DesignationsResource extends JsonResource
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
            'name' => $this->name,
            'operating_unit' => new OperatingUnitsResource($this->whenLoaded('operatingUnit')),
            'employees' => $this->whenLoaded('employeeDesignations', function () {
                return $this->employeeDesignations->map(function ($employeeDesignation) {
                    return [
                        'id' => $employeeDesignation->employee->id,
                        'employee_number' => $employeeDesignation->employee->employee_number,
                        'name' => $employeeDesignation->employee->personalInformation 
                            ? $employeeDesignation->employee->personalInformation->firstname . ' ' . 
                              $employeeDesignation->employee->personalInformation->lastname
                            : 'N/A',
                        'assumption_date' => $employeeDesignation->assumption_date,
                    ];
                });
            }),
            'edit_link' => $this->edit_link,
            'is_vsl' => $this->is_vsl ? 1 : 0,
        ];
    }
}

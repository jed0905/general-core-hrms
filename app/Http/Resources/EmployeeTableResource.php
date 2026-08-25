<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeTableResource extends JsonResource
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
            'employee_number' => $this->employee_number,
            // 'biometrics_id' => $this->biometrics_id,
            'biometric_id' => $this->biometricIds->pluck('biometric_id'),
            'fullname_desc' => $this->personalInformation->getFullNameAttributeDesc(),
            'department' => new DepartmentsResource($this->whenLoaded('department')),
            'employee_type' => $this->employee_type,
            'position' => new PositionsResource($this->whenLoaded('position')),
            'job_status' => new JobStatusResource($this->whenLoaded('jobStatus')),
            'redirect_link' => $this->redirect_link,
            'operating_unit' => new OperatingUnitsResource($this->whenLoaded('operatingUnit')),
        ];
    }
}

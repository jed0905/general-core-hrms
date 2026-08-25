<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeJobDetailsResource extends JsonResource
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

            'photo' => $this->photo,

            // Job Details
            'date_hired' => $this->date_hired,
            'biometrics_id' => $this->biometrics_id,
            'detailed_at' => $this->detailed_at,
            'operating_unit_id' => $this->operating_unit_id,
            'department_id' => $this->department_id,
            'employee_type' => $this->employee_type,
            'job_status_id' => $this->job_status_id,
            'position_id' => $this->position_id,
            'position_name' => $this->position?->government_position->name,
            'parenthetical_title' => $this->parenthetical_title,
            'salary_step_id' => $this->salary_step_id,
            'custom_hourly_rate' => $this->custom_hourly_rate,
            'custom_daily_rate' => $this->custom_daily_rate,
            'designations' => $this->employeeDesignations,
            'biometric_ids' => $this->whenLoaded('biometricIds', function () {
                return $this->biometricIds->map(function ($bio) {
                    return [
                        'id' => $bio->id,
                        'biometric_id' => $bio->biometric_id,
                    ];
                });
            }),
            'immediate_supervisor_id' => $this->immediate_supervisor_id,
            'immediate_supervisor_designation' => $this->immediate_supervisor_designation,
            'higher_supervisor_id' => $this->higher_supervisor_id,
            'higher_supervisor_designation' => $this->higher_supervisor_designation,
            'subordinates' => SubordinateResource::collection($this->whenLoaded('subordinates')),
            'date_separated' => $this->date_separated,
            'gov_issued_id' => $this->gov_issued_id,
            'gov_id_number' => $this->gov_id_number,
            'date_issue' => $this->date_issue,
            'place_issue' => $this->place_issue,
            'separation_reason' => $this->separation_reason,
        ];
    }
}

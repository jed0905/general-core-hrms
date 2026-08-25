<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeWorkExperienceResource extends JsonResource
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
            'from' => $this->from,
            'to' => $this->to,
            'position_title' => $this->position_title,
            'department_agency' => $this->department_agency,
            'monthly_salary' => $this->monthly_salary,
            'salary_grade' => $this->salary_grade,
            'status_of_appointment' => $this->status_of_appointment,
            'government_service' => $this->government_service
        ];
    }
}

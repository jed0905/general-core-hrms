<?php

namespace App\Http\Resources;

use App\Models\SalaryStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
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
            'biometrics_id' => $this->biometrics_id,
            'photo' => $this->photo,

            'user_id' => $this->user_id,
            'role_id' => $this->role_id,
            'department' => new DepartmentsResource($this->whenLoaded('department')),
            'employee_type' => $this->employee_type,
            'position' => new PositionsResource($this->whenLoaded('position')),
            'job_status' => new JobStatusResource($this->whenLoaded('jobStatus')),
            'salary_grade' => new SalaryGradeResource($this->whenLoaded('salaryGrade')),
            'salary_step' => new SalaryStepResource($this->whenLoaded('salaryStep')),
            'date_hired' => $this->date_hired,
            'spouse_id' => fn() => $this->whenLoaded('spouse', function () {
                return $this->spouse ? $this->spouse->name : null;
            }),
            'personal_information' => new PersonalInformationResource($this->whenLoaded('personalInformation')),
            'family_background' => fn() => $this->whenLoaded('familyBackground'),
            'gov_issued_id' => $this->gov_issued_id,
            'gov_id_number' => $this->gov_id_number,
            'date_issue' => $this->date_issue,
            'place_issue' => $this->place_issue,
            'edit_link' => $this->edit_link,
            'operating_unit' => new OperatingUnitsResource($this->whenLoaded('operatingUnit')),
            'weekly_shift_template' => new WeeklyShiftTemplateResource($this->whenLoaded('weeklyShiftTemplate')),
            // Relationships
            // 'user' => new UserResource($this->whenLoaded('user')),
            // 'department' => new DepartmentResource($this->whenLoaded('department')),
            // 'designation' => new DesignationResource($this->whenLoaded('designation')),
            // 'jobStatus' => new JobStatusResource($this->whenLoaded('jobStatus')),
            // 'employeeType' => new EmployeeTypeResource($this->whenLoaded('employeeType')),
            'employee_movement' => EmployeeMovementResource::collection(
                $this->whenLoaded('employeeMovement')
            ),
        ];
    }
}

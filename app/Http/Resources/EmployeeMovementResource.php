<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeMovementResource extends JsonResource
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
            'movement_type' => $this->movement_type,
            'previous_position_id' => $this->previous_position_id,
            'previous_department_id' => $this->previous_department_id,
            'previous_designation_id' => $this->previous_designation_id,
            'previous_operating_unit_id' => $this->previous_operating_unit_id,
            'previous_salary' => $this->previous_salary,
            'previous_salary_grade' => $this->previous_salary_grade,
            'previous_salary_step' => $this->previous_salary_step,
            'new_position_id' => $this->new_position_id,
            'new_department_id' => $this->new_department_id,
            'new_designation_id' => $this->new_designation_id,
            'new_operating_unit_id' => $this->new_operating_unit_id,
            'new_salary' => $this->new_salary,
            'new_salary_grade' => $this->new_salary_grade,
            'new_salary_step' => $this->new_salary_step,
            'effective_date' => $this->effective_date,
            'approval_date' => $this->approval_date,
            'implementation_date' => $this->implementation_date,
            'status' => $this->status,
            'reason' => $this->reason,
            'remarks' => $this->remarks,
            'justification' => $this->justification,
            'approved_by' => $this->approved_by,
            'requested_by' => $this->requested_by,
            'document_number' => $this->document_number,
            'document_type' => $this->document_type,
            'document_file_path' => $this->document_file_path,
            'is_permanent' => $this->is_permanent,
            'is_temporary' => $this->is_temporary,
            'temporary_end_date' => $this->temporary_end_date,
            'conditions' => $this->conditions,
        ];
    }
}

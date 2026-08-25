<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeLeaveLedgerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'employee_id' => $this->id,
            'is_vsl' => match ($this->employee_type) {
                'Non-Teaching' => true,
                'Teaching' => $this->employeeDesignations->isNotEmpty(),
                default => false,
            },
            'date_hired' => $this->date_hired,
            'full_name_asc' => $this->whenLoaded('personalInformation', fn() => $this->personalInformation->getFullNameAttributeDesc()),
            'office_division' => $this->whenLoaded('department', fn() => $this->department->name),
            'leave_credits_history' => EmployeeLeaveCreditsHistoryResource::collection($this->leaveCreditsHistory),
        ];
    }
}

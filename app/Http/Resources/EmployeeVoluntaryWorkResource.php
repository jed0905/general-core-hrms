<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeVoluntaryWorkResource extends JsonResource
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
            'name_of_organization' => $this->name_of_organization,
            'from' => $this->from,
            'to' => $this->to,
            'hours' => $this->hours,
            'position' => $this->position,
        ];
    }
}

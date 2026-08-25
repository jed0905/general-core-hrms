<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeLearningAndDevelopmentResource extends JsonResource
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
            'title' => $this->title,
            'from' => $this->from,
            'to' => $this->to,
            'training_hours' => $this->training_hours,
            'type_of_ld' => $this->type_of_ld,
            'conducted_by' => $this->conducted_by,
        ];
    }
}

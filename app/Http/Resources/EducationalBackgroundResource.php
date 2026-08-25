<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EducationalBackgroundResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'employee_id' => $this->employee_id ?? '',
            'name_of_school' => $this->name_of_school ?? '',
            'degree_course' => $this->degree_course ?? '',
            'period_from' => $this->period_from ?? '',
            'period_to' => $this->period_to ?? '',
            'highest_level' => $this->highest_level ?? '',
            'year_graduated' => $this->year_graduated ?? '',
            'academic_award' => $this->academic_award ?? '',
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeOtherInformationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request)
    {
        return [
            'special_skills' => EmployeeSpecialSkillsResource::collection($this['special_skills']),
            'non_academic_distinctions' => EmployeeNonAcademicDistinctionsResource::collection($this['non_academic_distinctions']),
            'memberships' => EmployeeMembershipsResource::collection($this['memberships'])
        ];
    }
}

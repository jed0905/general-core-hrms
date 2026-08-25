<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeEducationalBackgroundResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'elementary' => EducationalBackgroundResource::collection($this['elementaries']),
            'secondary' => EducationalBackgroundResource::collection($this['secondaries']),
            'vocational' => EducationalBackgroundResource::collection($this['vocationals']),
            'college' => EducationalBackgroundResource::collection($this['colleges']),
            'graduate_studies' => EducationalBackgroundResource::collection($this['graduate_studies']),
        ];
    }
}

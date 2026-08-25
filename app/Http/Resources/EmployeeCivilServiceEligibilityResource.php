<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeCivilServiceEligibilityResource extends JsonResource
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
            'eligibility' => $this->eligibility ?? '',
            'rating' => $this->rating ?? '',
            'date_of_examination' => $this->date_of_examination ?? '',
            'place_of_examination' => $this->place_of_examination ?? '',
            'license_no' => $this->license_no ?? '',
            'date_of_validity' => $this->date_of_validity ?? '',
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonalInformationResource extends JsonResource
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
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'suffix' => $this->suffix,
            'sex' => $this->sex,
            'civil_status' => $this->civil_status,
            'email' => $this->email,
            'birth_date' => $this->birth_date,
            'birth_place' => $this->birth_place,
            'citizenship' => $this->citizenship,
            'psa_verified' => $this->psa_verified,
            'full_name_desc' => $this->getFullNameAttributeDesc(),
            'full_name_asc' => $this->getFullNameAttributeAsc(),
        ];
    }
}

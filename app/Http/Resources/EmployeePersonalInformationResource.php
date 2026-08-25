<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeePersonalInformationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->employee_id,
            'firstname' => $this->firstname,
            'middlename' => $this->middlename,
            'lastname' => $this->lastname,
            'suffix' => $this->suffix,
            'date_of_birth' => $this->date_of_birth,
            'place_of_birth' => $this->place_of_birth,
            'sex' => $this->sex,
            'civil_status' => $this->civil_status,
            'height' => $this->height,
            'weight' => $this->weight,
            'blood_type' => $this->blood_type,
            'citizenship' => $this->citizenship,
            'dual_citizenship_type' => $this->dual_citizenship_type,
            'dual_citizenship_country' => $this->dual_citizenship_country,
            'residential_province' => $this->residential_province,
            'residential_city_municipality' => $this->residential_city_municipality,
            'residential_barangay' => $this->residential_barangay,
            'residential_subdivision' => $this->residential_subdivision,
            'residential_street' => $this->residential_street,
            'residential_house_no' => $this->residential_house_no,
            'residential_zip_code' => $this->residential_zip_code,
            'permanent_province' => $this->permanent_province,
            'permanent_city_municipality' => $this->permanent_city_municipality,
            'permanent_barangay' => $this->permanent_barangay,
            'permanent_subdivision' => $this->permanent_subdivision,
            'permanent_street' => $this->permanent_street,
            'permanent_house_no' => $this->permanent_house_no,
            'permanent_zip_code' => $this->permanent_zip_code,
            'telephone_no' => $this->telephone_no,
            'mobile_no' => $this->mobile_no,
            'email' => $this->email,
            'gsis_id_no' => $this->gsis_id_no,
            'pag_ibig_id_no' => $this->pag_ibig_id_no,
            'philhealth_id_no' => $this->philhealth_id_no,
            'sss_id_no' => $this->sss_id_no,
            'tin_id_no' => $this->tin_id_no,
            'psa_verified' => $this->psa_verified,
            'e_signature_path' => $this->e_signature_path,
        ];
    }
}

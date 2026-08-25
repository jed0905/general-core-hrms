<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MyProfilePersonalInformationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, // Employee ID
            'firstname' => $this->personalInformation->firstname,
            'middlename' => $this->personalInformation->middlename,
            'lastname' => $this->personalInformation->lastname,
            'suffix' => $this->personalInformation->suffix,
            'date_of_birth' => $this->personalInformation->date_of_birth,
            'place_of_birth' => $this->personalInformation->place_of_birth,
            'sex' => $this->personalInformation->sex,
            'civil_status' => $this->personalInformation->civil_status,
            'height' => $this->personalInformation->height,
            'weight' => $this->personalInformation->weight,
            'blood_type' => $this->personalInformation->blood_type,
            'citizenship' => $this->personalInformation->citizenship,
            'dual_citizenship_type' => $this->personalInformation->dual_citizenship_type,
            'dual_citizenship_country' => $this->personalInformation->dual_citizenship_country,
            'residential_province' => $this->personalInformation->residential_province,
            'residential_city_municipality' => $this->personalInformation->residential_city_municipality,
            'residential_barangay' => $this->personalInformation->residential_barangay,
            'residential_subdivision' => $this->personalInformation->residential_subdivision,
            'residential_street' => $this->personalInformation->residential_street,
            'residential_house_no' => $this->personalInformation->residential_house_no,
            'residential_zip_code' => $this->personalInformation->residential_zip_code,
            'permanent_province' => $this->personalInformation->permanent_province,
            'permanent_city_municipality' => $this->personalInformation->permanent_city_municipality,
            'permanent_barangay' => $this->personalInformation->permanent_barangay,
            'permanent_subdivision' => $this->personalInformation->permanent_subdivision,
            'permanent_street' => $this->personalInformation->permanent_street,
            'permanent_house_no' => $this->personalInformation->permanent_house_no,
            'permanent_zip_code' => $this->personalInformation->permanent_zip_code,
            'telephone_no' => $this->personalInformation->telephone_no,
            'mobile_no' => $this->personalInformation->mobile_no,
            'email' => $this->personalInformation->email,
            'gsis_id_no' => $this->personalInformation->gsis_id_no,
            'pag_ibig_id_no' => $this->personalInformation->pag_ibig_id_no,
            'philhealth_id_no' => $this->personalInformation->philhealth_id_no,
            'sss_id_no' => $this->personalInformation->sss_id_no,
            'tin_id_no' => $this->personalInformation->tin_id_no,
            'agency_employee_no' => $this->employee_number,
        ];
    }
}

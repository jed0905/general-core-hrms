<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonalInformation extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'firstname',
        'lastname',
        'middlename',
        'suffix',
        'date_of_birth',
        'place_of_birth',
        'sex',
        'civil_status',
        'height',
        'weight',
        'blood_type',
        'gsis_id_no',
        'pag_ibig_id_no',
        'philhealth_id_no',
        'sss_id_no',
        'tin_id_no',
        'citizenship',
        'dual_citizenship_type',
        'dual_citizenship_country',
        'residential_house_no',
        'residential_street',
        'residential_subdivision',
        'residential_barangay',
        'residential_city_municipality',
        'residential_province',
        'residential_zip_code',
        'permanent_house_no',
        'permanent_street',
        'permanent_subdivision',
        'permanent_barangay',
        'permanent_city_municipality',
        'permanent_province',
        'permanent_zip_code',
        'telephone_no',
        'mobile_no',
        'email',
        'e_signature_path',
        'psa_verified',
    ];

    protected $casts = [
        'psa_verified' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function getFirstNameAttribute($value)
    {
        return trim($value);
    }

    public function getFullNameAttributeAsc()
    {
        if (empty($this->middlename)) {
            return trim("{$this->firstname} {$this->lastname}");
        } else {
            return trim("{$this->firstname} {$this->middlename} {$this->lastname}");
        }
    }

    public function getFullNameAttributeDesc()
    {
        $name = "{$this->lastname}, {$this->firstname}";

        if (!empty($this->middlename)) {
            $name .= " {$this->middlename}";
        }

        if (!empty($this->suffix)) {
            $name .= " {$this->suffix}";
        }

        return trim($name);
    }

    // Full name with middle initial (Firstname M. Lastname)
    public function getFullNameWithMiddleInitialAttribute()
    {
        if (!empty($this->middlename)) {
            $middleInitial = strtoupper(substr($this->middlename, 0, 1)) . '.';
            return trim("{$this->firstname} {$middleInitial} {$this->lastname} {$this->suffix}");
        }

        return trim("{$this->firstname} {$this->lastname} {$this->suffix}");
    }

    // Firstname Lastname only (ignores middle name completely)
    public function getFirstLastNameAttribute()
    {
        return trim("{$this->firstname} {$this->lastname} {$this->suffix}");
    }

    public function getEsignatureUrlAttribute()
    {
        if ($this->e_signature_path) {
            return asset('storage/' . $this->e_signature_path);
        }

        return null;
    }
}

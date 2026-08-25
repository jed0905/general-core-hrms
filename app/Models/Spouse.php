<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Spouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'spouse_lastname',
        'spouse_firstname',
        'spouse_middlename',
        'spouse_suffix',
        'occupation',
        'employer_business_name',
        'business_address',
        'telephone_no',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FamilyBackground extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'father_lastname',
        'father_firstname',
        'father_middlename',
        'father_suffix',
        'mother_lastname',
        'mother_firstname',
        'mother_middlename',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CivilServiceEligibility extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'eligibility',
        'rating',
        'date_of_examination',
        'place_of_examination',
        'license_no',
        'date_of_validity',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}

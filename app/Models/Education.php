<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Education extends Model
{
    protected $fillable = [
        'employee_id',
        'level',
        'institute',
        'major_specialization',
        'year',
        'gpa_score',
        'start_date',
        'end_date'
    ];
}

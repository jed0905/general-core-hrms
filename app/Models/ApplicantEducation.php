<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicantEducation extends Model
{
    protected $table = 'applicant_educations';

    protected $fillable = [
        'applicant_id',
        'level',
        'institute',
        'degree',
        'major_specialization',
        'start_date',
        'end_date',
        'is_completed',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'applicant_id' => 'integer',
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'is_completed' => 'boolean',
        ];
    }
}

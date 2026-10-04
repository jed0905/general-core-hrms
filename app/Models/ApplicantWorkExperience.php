<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Same canonical fields as employee work experience: from / to / notes.
 */
class ApplicantWorkExperience extends Model
{
    protected $fillable = [
        'applicant_id',
        'company',
        'job_title',
        'from',
        'to',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'applicant_id' => 'integer',
            'from' => 'date:Y-m-d',
            'to' => 'date:Y-m-d',
        ];
    }
}

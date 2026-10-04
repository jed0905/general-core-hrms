<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A configurable number series (prefix + zero-padded counter + suffix).
 * Numbers are only issued through NumberSequenceService, which locks the row.
 */
class NumberSequence extends Model
{
    public const EMPLOYEE_NUMBER = 'employee_number';

    public const JOB_REQUISITION_NUMBER = 'job_requisition_number';

    public const VACANCY_NUMBER = 'vacancy_number';

    public const APPLICANT_NUMBER = 'applicant_number';

    public const APPLICATION_NUMBER = 'application_number';

    public const JOB_OFFER_NUMBER = 'job_offer_number';

    public const RESET_YEARLY = 'yearly';

    protected $fillable = [
        'key',
        'name',
        'prefix',
        'suffix',
        'padding',
        'next_number',
        'auto_generate',
        'reset_period',
        'current_period',
    ];

    protected function casts(): array
    {
        return [
            'padding' => 'integer',
            'next_number' => 'integer',
            'auto_generate' => 'boolean',
        ];
    }
}

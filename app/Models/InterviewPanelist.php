<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An employee assigned to an interview panel. The assignment is what lets the
 * employee see that interview and write their own scorecard; it is not a role.
 */
class InterviewPanelist extends Model
{
    protected $fillable = [
        'application_interview_id',
        'employee_id',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'application_interview_id' => 'integer',
            'employee_id' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    public function interview(): BelongsTo
    {
        return $this->belongsTo(ApplicationInterview::class, 'application_interview_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

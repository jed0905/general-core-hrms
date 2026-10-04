<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A person who may apply to vacancies. Not an employee (employee_id is only
 * set for internal candidates; converted_employee_id once hired, later phase).
 */
class Applicant extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'applicant_number',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'preferred_name',
        'email',
        'phone',
        'alternate_phone',
        'normalized_email',
        'phone_key',
        'alternate_phone_key',
        'address',
        'recruitment_source_id',
        'source_details',
        'privacy_consent',
        'privacy_consented_at',
        'is_internal',
        'employee_id',
        'converted_employee_id',
        'status',
        'created_by',
    ];

    /** Matching keys are internal. */
    protected $hidden = [
        'normalized_email',
        'phone_key',
        'alternate_phone_key',
    ];

    protected $appends = ['full_name'];

    protected function casts(): array
    {
        return [
            'recruitment_source_id' => 'integer',
            'privacy_consent' => 'boolean',
            'privacy_consented_at' => 'datetime',
            'is_internal' => 'boolean',
            'employee_id' => 'integer',
            'converted_employee_id' => 'integer',
            'created_by' => 'integer',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name, $this->suffix])));
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(RecruitmentSource::class, 'recruitment_source_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function education(): HasMany
    {
        return $this->hasMany(ApplicantEducation::class);
    }

    public function workExperience(): HasMany
    {
        return $this->hasMany(ApplicantWorkExperience::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicantDocument::class);
    }

    /** Careers-portal login, if the candidate has one. */
    public function account(): HasOne
    {
        return $this->hasOne(ApplicantAccount::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * The hand-off of one application (with its accepted offer) to Core HR.
 * Written once by ApplicationConversionService, in the same transaction as the
 * employee it created or linked; never updated or deleted. It answers "which
 * application and offer produced this employee?".
 */
class ApplicationConversion extends Model
{
    public const TYPE_NEW_EMPLOYEE = 'new_employee';

    public const TYPE_EXISTING_EMPLOYEE = 'existing_employee';

    public const ACCOUNT_CREATED = 'created';

    public const ACCOUNT_EXISTING = 'existing';

    public const ACCOUNT_NONE = 'none';

    protected $fillable = [
        'application_id',
        'applicant_id',
        'job_offer_id',
        'employee_id',
        'conversion_type',
        'employee_number',
        'employee_movement_id',
        'start_date',
        'user_account',
        'user_id',
        'education_copied',
        'work_experience_copied',
        'documents_copied',
        'notices',
        'converted_by',
        'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'application_id' => 'integer',
            'applicant_id' => 'integer',
            'job_offer_id' => 'integer',
            'employee_id' => 'integer',
            'employee_movement_id' => 'integer',
            'user_id' => 'integer',
            'start_date' => 'date:Y-m-d',
            'education_copied' => 'integer',
            'work_experience_copied' => 'integer',
            'documents_copied' => 'integer',
            'notices' => 'array',
            'converted_by' => 'integer',
            'converted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('A completed conversion is immutable.'));
        static::deleting(fn () => throw new LogicException('A completed conversion is immutable.'));
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(JobOffer::class, 'job_offer_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(EmployeeMovement::class, 'employee_movement_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function converter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationConversionDocument::class);
    }
}

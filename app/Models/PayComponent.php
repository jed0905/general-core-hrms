<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something that can appear on a payslip. line_class decides its effect on net
 * pay; employer contributions never reduce it. System components (BASIC, ABSENCE,
 * ...) are emitted by the engine itself and can't be deleted or reclassified.
 */
class PayComponent extends Model
{
    public const CLASS_EARNING = 'earning';

    public const CLASS_EMPLOYEE_DEDUCTION = 'employee_deduction';

    public const CLASS_EMPLOYER_CONTRIBUTION = 'employer_contribution';

    public const CLASS_INFORMATIONAL = 'informational';

    public const LINE_CLASSES = [self::CLASS_EARNING, self::CLASS_EMPLOYEE_DEDUCTION, self::CLASS_EMPLOYER_CONTRIBUTION, self::CLASS_INFORMATIONAL];

    public const CATEGORIES = [
        'basic', 'attendance', 'premium', 'allowance', 'bonus', 'commission', 'other_earning',
        'loan', 'voluntary_deduction', 'other_deduction', 'employer_benefit', 'informational',
    ];

    protected $fillable = [
        'code', 'name', 'line_class', 'category', 'legal_entity_id', 'priority', 'description', 'is_system', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function treatments(): HasMany
    {
        return $this->hasMany(PayComponentTreatment::class);
    }

    public function treatmentFor(string $countryCode): ?PayComponentTreatment
    {
        return $this->relationLoaded('treatments')
            ? $this->treatments->firstWhere('country_code', $countryCode)
            : $this->treatments()->where('country_code', $countryCode)->first();
    }
}

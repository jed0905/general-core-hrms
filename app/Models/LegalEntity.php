<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A registered employer. Country, currency, timezone and locale are the entity's
 * own; nothing falls back to the application defaults.
 */
class LegalEntity extends Model
{
    protected $fillable = [
        'code', 'name', 'legal_name', 'country_code', 'subdivision_code', 'registration_number',
        'tax_identifier', 'functional_currency', 'timezone', 'locale', 'fiscal_year_start_month',
        'address', 'localization_settings', 'organization_general_information_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year_start_month' => 'integer',
            'localization_settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function payGroups(): HasMany
    {
        return $this->hasMany(PayGroup::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationGeneralInformation::class, 'organization_general_information_id');
    }

    public function payrollUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'payroll_entity_user')->withTimestamps();
    }
}

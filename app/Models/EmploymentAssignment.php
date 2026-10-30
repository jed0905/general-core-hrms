<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Employee x legal entity x pay group x work location over a date range.
 * payroll_country_code is the resolved payroll jurisdiction; nationality never
 * decides it.
 */
class EmploymentAssignment extends Model
{
    protected $fillable = [
        'employee_id', 'legal_entity_id', 'pay_group_id', 'location_id', 'work_country_code',
        'work_subdivision_code', 'payroll_country_code', 'jurisdiction_override_reason',
        'effective_from', 'effective_to', 'is_primary', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date:Y-m-d',
            'effective_to' => 'date:Y-m-d',
            'is_primary' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function payGroup(): BelongsTo
    {
        return $this->belongsTo(PayGroup::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function compensations(): HasMany
    {
        return $this->hasMany(EmployeeCompensation::class);
    }

    public function payComponents(): HasMany
    {
        return $this->hasMany(EmployeePayComponent::class);
    }

    /** Assignments overlapping [$from, $to]. */
    public function scopeOverlapping(Builder $query, string $from, string $to): Builder
    {
        return $query->where('effective_from', '<=', $to)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from));
    }
}

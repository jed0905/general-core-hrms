<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Salary ranges per pay grade, in one currency and basis, over a date range.
 */
class SalaryStructure extends Model
{
    protected $fillable = ['legal_entity_id', 'name', 'currency', 'basis', 'effective_from', 'effective_to', 'is_active'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date:Y-m-d',
            'effective_to' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(SalaryStructureGrade::class);
    }
}

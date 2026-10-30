<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Effective-dated, evidenced tax residency. Never derived from nationality or address.
 */
class EmployeeTaxResidency extends Model
{
    public const STATUSES = ['resident', 'non_resident', 'other'];

    protected $fillable = [
        'employee_id', 'country_code', 'residency_status', 'effective_from', 'effective_to',
        'evidence_reference', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date:Y-m-d',
            'effective_to' => 'date:Y-m-d',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

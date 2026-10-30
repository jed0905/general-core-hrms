<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recurring allowance or deduction of an assignment, per pay period or per
 * month. total_limit caps the cumulative amount (e.g. a loan principal).
 */
class EmployeePayComponent extends Model
{
    public const BASES = ['per_period', 'monthly'];

    protected $fillable = [
        'employee_id', 'employment_assignment_id', 'pay_component_id', 'amount', 'currency', 'amount_basis',
        'total_limit', 'effective_from', 'effective_to', 'remarks', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'total_limit' => 'decimal:4',
            'effective_from' => 'date:Y-m-d',
            'effective_to' => 'date:Y-m-d',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(EmploymentAssignment::class, 'employment_assignment_id');
    }

    public function payComponent(): BelongsTo
    {
        return $this->belongsTo(PayComponent::class);
    }
}
